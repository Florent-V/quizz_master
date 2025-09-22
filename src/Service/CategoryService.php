<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Quiz\Exception\QuizNotFoundException;
use App\Quiz\Exception\QuizUnprocessable;
use App\Repository\CategoryRepository;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

readonly class CategoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private QuestionRepository $questionRepository,
        private Filesystem $filesystem,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    public function getOrCreateCategory(string $name, ?Category $parent = null): Category
    {
        $criteria = ['name' => $name, 'parent' => $parent];
        $category = $this->categoryRepository->findOneBy($criteria);

        if (!$category) {
            $category = new Category();
            $category->setName($name);
            $category->setIsActive(false);
            if ($parent) {
                $category->setParent($parent);
            }
            $this->entityManager->persist($category);
            $this->entityManager->flush();
        }

        return $category;
    }

    public function restore(int $categoryId): void
    {
        // Désactiver temporairement le filtre SoftDeleteable
        $this->entityManager->getFilters()->disable('softdeleteable');
        // Récupérer la catégorie
        $category = $this->categoryRepository->find($categoryId);

        if (!$category instanceof Category) {
            throw new QuizNotFoundException('Catégorie non trouvée');
        }

        if (null === $category->getDeletedAt()) {
            throw new QuizUnprocessable('Cette catégorie n\'est pas supprimée');
        }

        $category->setDeletedAt(null);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        // Réactiver le filtre
        $this->entityManager->getFilters()->enable('softdeleteable');
    }

    public function duplicate(int $categoryId): Category
    {
        // Récupérer la catégorie
        $category = $this->categoryRepository->find($categoryId);

        if (!$category instanceof Category) {
            throw new QuizNotFoundException('Catégorie non trouvée');
        }

        $duplicate = new Category();
        $duplicate->setName($category->getName() . ' (Copie)');
        $duplicate->setDescription($category->getDescription());
        $duplicate->setIcon($category->getIcon());
        $duplicate->setParent($category->getParent());

        // Copier l'image si elle existe
        if ($category->getImageName()) {
            $uploadsPath     = $this->parameterBag->get('kernel.project_dir') . '/public/uploads/images/categories/';
            $sourceImagePath = $uploadsPath . $category->getImageName();

            if ($this->filesystem->exists($sourceImagePath)) {
                // Crée un objet File à partir de l'image existante
                $file = new File($sourceImagePath);
                // Assigne le fichier à la nouvelle entité. VichUploader s'occupera de la copie.
                $duplicate->setImageFile($file);
                // Le imageName sera défini par VichUploader après le flush
                // $duplicate->setImageName($category->getImageName()); // Ne pas définir directement
            }
        }

        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();

        return $duplicate;
    }

    /**
     * Nettoie les catégories orphelines ou en double.
     *
     * @return array<string, int>
     */
    public function cleanupCategories(): array
    {
        $results = [
            'orphaned_fixed'    => 0,
            'duplicates_merged' => 0,
            'empty_removed'     => 0,
        ];

        // Recherche des catégories avec des parents supprimés
        $orphaned = $this->categoryRepository->findOrphanedCategories();
        foreach ($orphaned as $category) {
            $category->setDeletedAt(new \DateTime());
            ++$results['orphaned_fixed'];
        }

        // Recherche des doublons potentiels (même nom et même parent)
        $duplicates = $this->categoryRepository->findDuplicateCategories();
        foreach ($duplicates as $duplicateGroup) {
            $this->mergeDuplicateCategories($duplicateGroup);
            ++$results['duplicates_merged'];
        }

        $this->entityManager->flush();

        return $results;
    }

    /**
     * @param array<Category> $duplicates
     */
    private function mergeDuplicateCategories(array $duplicates): void
    {
        if (count($duplicates) < 2) {
            return;
        }

        // Garde la première catégorie comme "master"
        $master = array_shift($duplicates);

        // Transfère toutes les questions et sous-catégories vers le master
        foreach ($duplicates as $duplicate) {
            // Transfère les questions
            foreach ($duplicate->getQuestions() as $question) {
                $question->setCategory($master);
            }

            // Transfère les sous-catégories
            foreach ($duplicate->getChildren() as $child) {
                $child->setParent($master);
            }

            // Supprime le doublon
            $this->entityManager->remove($duplicate);
        }
    }

    public function getTotalQuestionsCount(Category $category): int
    {
        $count = $category->getQuestions()->count();

        foreach ($category->getChildren() as $child) {
            $count += $child->getQuestions()->count();
        }

        return $count;
    }

    /**
     * @return array<string,mixed>
     */
    public function getStatistics(int $categoryId): array
    {
        $category = $this->categoryRepository->find($categoryId);

        if (!$category instanceof Category) {
            throw new QuizNotFoundException('Catégorie non trouvée');
        }

        return [
            'category'          => $category,
            'direct_questions'  => $category->getQuestions()->count(),
            'total_questions'   => $this->getTotalQuestionsCount($category),
            'children_count'    => $category->getChildren()->count(),
            'depth_level'       => $category->getLvl(),
            'descendants_count' => $category->getActiveChildrenCount(),
            'created_days_ago'  => $category->getCreatedAt() ?
                (new \DateTime())->diff($category->getCreatedAt())->days : 0,
        ];
    }

    /**
     * Retrieves all active categories (not deleted) ordered by name.
     *
     * @return Category[] Array of active Category entities
     */
    public function getActiveCategories(): array
    {
        return $this->categoryRepository->findBy(['deletedAt' => null], ['name' => 'ASC']);
    }

    /**
     * Retrieves categorized lists of active categories.
     *
     * @return array{
     *     categories: Category[],
     *     parentCategories: Category[],
     *     childCategories: Category[]
     * }
     */
    public function getCategorizedLists(): array
    {
        $categories       = $this->getActiveCategories();
        $parentCategories = [];
        $childCategories  = [];

        foreach ($categories as $category) {
            if (null === $category->getParent()) {
                $parentCategories[] = $category;
                continue; // Passe à l'itération suivante sans "else"
            }
            $childCategories[] = $category;
        }

        return [
            'categories'       => $categories,
            'parentCategories' => $parentCategories,
            'childCategories'  => $childCategories,
        ];
    }

    /**
     * Builds a hierarchical structure of parent categories with their children.
     *
     * @param Category[] $parentCategories Array of parent Category entities
     * @param Category[] $childCategories  Array of child Category entities
     *
     * @return array<int, array{parent: Category, children: Category[]}>
     */
    private function buildParentCategoryStructure(array $parentCategories, array $childCategories): array
    {
        $categories = [];
        foreach ($parentCategories as $category) {
            $categories[$category->getId()] = [
                'parent'   => $category,
                'children' => [],
            ];
        }

        foreach ($childCategories as $category) {
            $parentId = $category->getParent()->getId();
            if (isset($categories[$parentId])) {
                $categories[$parentId]['children'][] = $category;
            }
        }

        return $categories;
    }

    /**
     * Builds an array of statistics for each category, including question counts.
     *
     * @param array<int, array{parent: Category, children: Category[]}> $categories Hierarchical category structure
     *
     * @return array<int, array{
     *     category: Category,
     *     question_count: int,
     *     has_children: bool,
     *     level: int,
     *     is_parent: bool
     * }>
     */
    private function buildArrayStats(array $categories): array
    {
        $stats = [];
        // Créer les stats dans l'ordre parent/enfants
        foreach ($categories as $parentData) {
            $parent        = $parentData['parent'];
            $questionCount = $this->questionRepository->count([
                'category'  => $parent,
                'deletedAt' => null,
            ]);

            $stats[] = [
                'category'       => $parent,
                'question_count' => $questionCount,
                'has_children'   => !empty($parentData['children']),
                'level'          => $parent->getLvl(),
                'is_parent'      => true,
            ];

            foreach ($parentData['children'] as $child) {
                $childQuestionCount = $this->questionRepository->count([
                    'category'  => $child,
                    'deletedAt' => null,
                ]);

                $stats[] = [
                    'category'       => $child,
                    'question_count' => $childQuestionCount,
                    'has_children'   => false,
                    'level'          => $child->getLvl(),
                    'is_parent'      => false,
                ];
            }
        }

        return $stats;
    }

    /**
     * Retrieves category statistics based on categorized lists.
     *
     * @param array{
     *     categories: Category[],
     *     parentCategories: Category[],
     *     childCategories: Category[]
     * } $categorizedCategories Categorized lists of categories
     *
     * @return array<int, array{
     *     category: Category,
     *     question_count: int,
     *     has_children: bool,
     *     level: int,
     *     is_parent: bool
     * }>
     */
    public function getCategoryStats(array $categorizedCategories): array
    {
        // Organiser par parent/enfant
        $parentCategories = $this->buildParentCategoryStructure(
            $categorizedCategories['parentCategories'],
            $categorizedCategories['childCategories']
        );

        return $this->buildArrayStats($parentCategories);
    }
}
