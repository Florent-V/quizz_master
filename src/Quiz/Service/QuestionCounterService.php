<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\Entity\Category;
use App\Entity\Difficulty;
use App\Repository\QuestionRepository;

/**
 * Service responsible for counting available questions.
 */
final readonly class QuestionCounterService
{
    public function __construct(
        private QuestionRepository $questionRepository,
    ) {
    }

    /**
     * Counts the total number of available questions for a given configuration.
     *
     * @param int[] $difficulties
     */
    public function countAvailableQuestions(
        ?Category $category = null,
        ?Category $subCategory = null,
        array $difficulties = [],
    ): int {
        $difficultyCounts = $this->questionRepository->getAvailableDifficultyCounts(
            $category,
            $subCategory
        );

        // If no difficulty is specified, all questions are available.
        if (empty($difficulties)) {
            return array_sum($difficultyCounts);
        }

        $total = 0;
        foreach ($difficulties as $difficultyId) {
            $total += $difficultyCounts[$difficultyId] ?? 0;
        }

        return $total;
    }

    /**
     * Counts the number of questions for a specific difficulty.
     */
    public function countQuestionsForDifficulty(
        Difficulty $difficulty,
        ?Category $category = null,
        ?Category $subCategory = null,
    ): int {
        $difficultyCounts = $this->questionRepository->getAvailableDifficultyCounts(
            $category,
            $subCategory
        );

        return $difficultyCounts[$difficulty->getId()] ?? 0;
    }

    /**
     * Checks if the minimum number of questions is reached.
     *
     * @param int[] $difficulties
     */
    public function hasMinimumQuestions(
        int $minimumRequired,
        ?Category $category = null,
        ?Category $subCategory = null,
        array $difficulties = [],
    ): bool {
        return $this->countAvailableQuestions($category, $subCategory, $difficulties) >= $minimumRequired;
    }
}
