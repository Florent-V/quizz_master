<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\Entity\Category;
use App\Entity\Difficulty;
use App\Repository\CategoryRepository;
use App\Repository\DifficultyRepository;
use App\Repository\ProposalRepository;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionAnswerRepository;
use App\Repository\QuizSessionRepository;
use App\Repository\UserRepository;

/**
 * Service responsible for counting available questions.
 */
final readonly class CounterService
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private DifficultyRepository $difficultyRepository,
        private QuestionRepository $questionRepository,
        private ProposalRepository $proposalRepository,
        private UserRepository $userRepository,
        private QuizSessionRepository $quizSessionRepository,
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
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

    public function countAllCategories(): int
    {
        return $this->categoryRepository->count(['deletedAt' => null]);
    }

    public function countAllDifficulties(): int
    {
        return $this->difficultyRepository->count();
    }

    public function countAllQuestions(): int
    {
        return $this->questionRepository->count(['deletedAt' => null]);
    }

    public function countAllProposals(): int
    {
        return $this->proposalRepository->count(['deletedAt' => null]);
    }

    public function countAllUsers(): int
    {
        return $this->userRepository->count();
    }

    public function countAllQuizSession(): int
    {
        return $this->quizSessionRepository->count(['deletedAt' => null]);
    }

    public function countAllQuizSessionAnswers(): int
    {
        return $this->quizSessionAnswerRepository->count(['deletedAt' => null]);
    }
}
