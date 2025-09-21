<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Quiz\Exception\QuizBadRequestException;
use App\Repository\CategoryRepository;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service to handle merging of categories.
 */
readonly class CategoryMergeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private QuestionRepository $questionRepository,
    ) {
    }

    /**
     * Validates the request parameters for a merge operation.
     *
     * @param int[]|null $sourceIds
     *
     * @throws QuizBadRequestException if source or target IDs are missing or invalid
     */
    public function validateMergeRequestParam(?array $sourceIds, ?int $targetId): void
    {
        if (empty($sourceIds) || !$targetId) {
            throw new QuizBadRequestException('Veuillez sélectionner les catégories source et la catégorie cible.');
        }

        if (in_array($targetId, $sourceIds)) {
            throw new QuizBadRequestException('La catégorie cible ne peut pas être dans les sources');
        }
    }

    /**
     * Merges parent categories by moving their children to the target category.
     *
     * @param int[] $sourceIds the IDs of the source parent categories
     * @param int   $targetId  the ID of the target parent category
     *
     * @throws \Exception
     *
     * @return array{children_moved: int, categories_deleted: int}
     */
    public function mergeParentCategories(array $sourceIds, int $targetId): array
    {
        $this->ensureTargetIdNotInSourceIds($sourceIds, $targetId);
        $targetCategory = $this->categoryRepository->find($targetId);
        $this->checkValidParentCategory($targetCategory, 'cible');

        $childrenMovedCount     = 0;
        $categoriesDeletedCount = 0;
        $this->entityManager->beginTransaction();

        try {
            foreach ($sourceIds as $sourceId) {
                $sourceCategory = $this->categoryRepository->find((int) $sourceId);
                $this->checkValidParentCategory($sourceCategory, 'source');

                // Déplacer les catégories enfants
                $childrenMovedCount += $this->moveChildren($sourceCategory, $targetCategory);

                // Marquer la catégorie source comme supprimée
                $this->softDeleteCategory($sourceCategory);
                ++$categoriesDeletedCount;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return [
                'children_moved'     => $childrenMovedCount,
                'categories_deleted' => $categoriesDeletedCount,
            ];
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Merges child categories by moving their questions to the target category.
     *
     * @param int[] $sourceIds the IDs of the source child categories
     * @param int   $targetId  the ID of the target child category
     *
     * @throws \Exception
     *
     * @return array{questions_moved: int, categories_deleted: int}
     */
    public function mergeChildCategories(array $sourceIds, int $targetId): array
    {
        $this->ensureTargetIdNotInSourceIds($sourceIds, $targetId);
        $targetCategory = $this->categoryRepository->find($targetId);
        $this->checkValidChildCategory($targetCategory);

        $questionsMovedCount    = 0;
        $categoriesDeletedCount = 0;
        $this->entityManager->beginTransaction();

        try {
            foreach ($sourceIds as $sourceId) {
                $sourceCategory = $this->categoryRepository->find((int) $sourceId);
                $this->checkValidChildCategory($sourceCategory);

                // Déplacer les questions dans la catégorie target
                $questionsMovedCount += $this->moveQuestions($sourceCategory, $targetCategory);

                // Marquer la catégorie source comme supprimée
                $this->softDeleteCategory($sourceCategory);
                ++$categoriesDeletedCount;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return [
                'questions_moved'    => $questionsMovedCount,
                'categories_deleted' => $categoriesDeletedCount,
            ];
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Moves questions from a source category to a target category.
     *
     * @return int the number of questions moved
     */
    private function moveQuestions(Category $source, Category $target): int
    {
        $count     = 0;
        $questions = $this->questionRepository->findBy([
            'category'  => $source,
            'deletedAt' => null,
        ]);

        foreach ($questions as $question) {
            $question->setCategory($target);
            $this->entityManager->persist($question);
            ++$count;
        }

        return $count;
    }

    /**
     * Moves child categories from a source parent category to a target parent category.
     *
     * @return int the number of child categories moved
     */
    private function moveChildren(Category $source, Category $target): int
    {
        $count = 0;
        foreach ($source->getChildren() as $child) {
            if (!$child->getDeletedAt()) {
                $child->setParent($target);
                $this->entityManager->persist($child);
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Soft deletes a category.
     */
    private function softDeleteCategory(Category $category): void
    {
        $category->setDeletedAt(new \DateTime());
        $this->entityManager->persist($category);
    }

    /**
     * Checks if a category exists and is valid (not null and not deleted).
     */
    private function isCategoryValid(?Category $category): bool
    {
        return null !== $category && !$category->getDeletedAt();
    }

    /**
     * Checks if a category is a valid child category (has a parent).
     */
    private function isValidChildCategory(?Category $category): bool
    {
        return $this->isCategoryValid($category) && null !== $category->getParent();
    }

    /**
     * Checks if a category is a valid parent category (has no parent).
     */
    private function isValidParentCategory(?Category $category): bool
    {
        return $this->isCategoryValid($category) && null === $category->getParent();
    }

    /**
     * Ensures that a category is a valid child category.
     *
     * @param string $type Category type (default: "cible")
     *
     * @throws QuizBadRequestException if the category is invalid or not a child category
     */
    public function checkValidChildCategory(?Category $category, string $type = 'cible'): void
    {
        if (!$this->isValidChildCategory($category)) {
            throw new QuizBadRequestException(sprintf('La Sous-Catégorie %s est invalide', $type));
        }
    }

    /**
     * Ensures that a category is a valid parent category.
     *
     * @param string $type Category type (default: "cible")
     *
     * @throws QuizBadRequestException if the category is invalid or not a parent category
     */
    public function checkValidParentCategory(?Category $category, string $type = 'cible'): void
    {
        if (!$this->isValidParentCategory($category)) {
            throw new QuizBadRequestException(sprintf('La Catégorie %s est invalide', $type));
        }
    }

    /**
     * Ensures that the target ID is not present in the source IDs list.
     *
     * @param int[] $sourceIds List of source IDs
     * @param int   $targetId  Target ID to check
     *
     * @throws QuizBadRequestException If the target ID is found in the source IDs
     */
    private function ensureTargetIdNotInSourceIds(array $sourceIds, int $targetId): void
    {
        if (in_array($targetId, $sourceIds, true)) {
            throw new QuizBadRequestException(
                'La catégorie cible ne doit pas être présente dans les catégories source'
            );
        }
    }
}
