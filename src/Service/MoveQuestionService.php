<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Question;
use App\Repository\CategoryRepository;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for moving questions between categories.
 */
readonly class MoveQuestionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private QuestionRepository $questionRepository,
        private CategoryMergeService $categoryMergeService,
    ) {
    }

    /**
     * Validates that the given parameter is an array of integers.
     *
     * @param mixed[] $questionIds The input to validate (expected: array<int>|null)
     *
     * @throws \InvalidArgumentException if the validation fails
     */
    public function validateArrayOfIds(?array $questionIds): void
    {
        if (null === $questionIds) {
            return;
        }
        if (count(array_filter($questionIds, fn ($id) => !is_int($id))) > 0) {
            throw new \InvalidArgumentException(
                'Les IDs des questions doivent être un tableau d\'entiers valides'
            );
        }
    }

    /**
     * Validates the parameters for moving questions.
     *
     * @param int[]|null $questionIds
     *
     * @throws \InvalidArgumentException if parameters are invalid
     */
    public function validateMoveQuestionsParam(int $sourceId, int $targetId, ?array $questionIds): void
    {
        // Vérifie que $sourceId et $targetId ne sont pas 0
        if (0 === $sourceId) {
            throw new \InvalidArgumentException('La catégorie source est requise.');
        }
        if (0 === $targetId) {
            throw new \InvalidArgumentException('La catégorie cible est requise.');
        }

        if ($sourceId === $targetId) {
            throw new \InvalidArgumentException('Les catégories source et cible doivent être différentes');
        }

        $this->validateArrayOfIds($questionIds);
    }

    /**
     * Ensures that the target ID is different from the source ID.
     *
     * @throws \InvalidArgumentException if the target ID is the same as the source ID
     */
    private function ensureTargetIdNotSameAsSourceId(int $sourceId, int $targetId): void
    {
        if ($targetId === $sourceId) {
            throw new \InvalidArgumentException(
                'Les catégories source et cible doivent être différentes.'
            );
        }
    }

    /**
     * Moves questions from one category to another.
     *
     * @param int[] $questionIds list of question IDs to move (if empty, all questions are moved)
     * @param int   $sourceId    the ID of the source category
     * @param int   $targetId    the ID of the target category
     *
     * @throws \Exception
     *
     * @return int the number of questions moved
     */
    public function moveQuestions(array $questionIds, int $sourceId, int $targetId): int
    {
        $this->ensureTargetIdNotSameAsSourceId($sourceId, $targetId);
        $targetCategory = $this->categoryRepository->find($targetId);
        $this->categoryMergeService->checkValidChildCategory($targetCategory, 'cible');

        $sourceCategory = $this->categoryRepository->find($sourceId);
        $this->categoryMergeService->checkValidChildCategory($sourceCategory, 'source');

        $movedCount = 0;
        $this->entityManager->beginTransaction();

        try {
            $questions = $this->getQuestionsToMove($questionIds, $sourceCategory);

            foreach ($questions as $question) {
                $question->setCategory($targetCategory);
                $this->entityManager->persist($question);
                ++$movedCount;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $movedCount;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Retrieves the questions to be moved.
     *
     * @param int[] $questionIds
     *
     * @return Question[]
     */
    private function getQuestionsToMove(array $questionIds, Category $sourceCategory): array
    {
        if (empty($questionIds)) {
            // Déplacer toutes les questions de la catégorie
            return $this->questionRepository->findBy([
                'category'  => $sourceCategory,
                'deletedAt' => null,
            ]);
        }

        return $this->questionRepository->findBy([
            'id'        => $questionIds,
            'category'  => $sourceCategory,
            'deletedAt' => null,
        ]);
    }
}
