<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

readonly class CategoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Filesystem $filesystem,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    public function restore(int $categoryId): void
    {
        // Désactiver temporairement le filtre SoftDeleteable
        $this->entityManager->getFilters()->disable('softdeleteable');

        $category = $this->entityManager->getRepository(Category::class)->find($categoryId);

        if (!$category instanceof Category) {
            throw new \InvalidArgumentException('Catégorie non trouvée');
        }

        if (null === $category->getDeletedAt()) {
            throw new \LogicException('Cette catégorie n\'est pas supprimée');
        }

        $category->setDeletedAt(null);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        // Réactiver le filtre
        $this->entityManager->getFilters()->enable('softdeleteable');
    }

    public function duplicate(int $categoryId): Category
    {
        $category = $this->entityManager->getRepository(Category::class)
            ->find($categoryId);

        if (!$category instanceof Category) {
            throw new \InvalidArgumentException('Catégorie non trouvée');
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
     * @return array<string,mixed>
     */
    public function getStatistics(int $categoryId): array
    {

        $category = $this->entityManager->getRepository(Category::class)
            ->find($categoryId);

        if (!$category instanceof Category) {
            throw new \InvalidArgumentException('Catégorie non trouvée');
        }

        return [
            'category'          => $category,
            'direct_questions'  => $category->getQuestions()->count(),
            'total_questions'   => $category->getTotalQuestionsCount(),
            'children_count'    => $category->getChildren()->count(),
            'depth_level'       => $category->getLvl(),
            'descendants_count' => $category->getActiveChildrenCount(),
            'created_days_ago'  => $category->getCreatedAt() ?
                (new \DateTime())->diff($category->getCreatedAt())->days : 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function exportToArray(): array
    {
        $categories = $this->entityManager->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.questions', 'q')
            ->addSelect('q')
            ->leftJoin('c.parent', 'p')
            ->addSelect('p')
            ->orderBy('c.lft', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(function (Category $category) {
            return [
                'id'              => $category->getId(),
                'name'            => $category->getName(),
                'slug'            => $category->getSlug(),
                'description'     => $category->getDescription(),
                'icon'            => $category->getIcon(),
                'level'           => $category->getLvl(),
                'parent'          => $category->getParent() ? $category->getParent()->getName() : null,
                'questions_count' => $category->getQuestions()->count(),
                'children_count'  => $category->getChildren()->count(),
                'created_at'      => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at'      => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'is_deleted'      => null !== $category->getDeletedAt(),
            ];
        }, $categories);
    }

    public function getTotalCount(): int
    {
        $this->entityManager->getFilters()->disable('softdeleteable');

        $count = $this->entityManager->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $this->entityManager->getFilters()->enable('softdeleteable');

        return (int) $count;
    }

    public function getDeletedCount(): int
    {
        $this->entityManager->getFilters()->disable('softdeleteable');

        $count = $this->entityManager->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.deletedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $this->entityManager->getFilters()->enable('softdeleteable');

        return (int) $count;
    }

    /**
     * @return Category[]
     */
    public function search(string $term, int $limit = 10): array
    {
        return $this->entityManager->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->where('c.name LIKE :term OR c.description LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
