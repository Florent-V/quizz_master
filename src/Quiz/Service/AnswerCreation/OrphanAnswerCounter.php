<?php

declare(strict_types=1);

namespace App\Quiz\Service\AnswerCreation;

use App\Entity\QuizSession;
use App\Repository\QuizSessionAnswerRepository;

/**
 * Service dedicated to a single responsibility: checking for an orphan answer in a quiz session.
 */
readonly class OrphanAnswerCounter
{
    public function __construct(
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
    ) {
    }

    public function count(QuizSession $quizSession): int
    {
        return $this->quizSessionAnswerRepository->countIncompleteByQuizSessionId($quizSession->getId());
    }
}
