<?php

declare(strict_types=1);

namespace App\Quiz\Service\AnswerCreation;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Repository\QuizSessionAnswerRepository;

readonly class SuddenDeathAnswerStrategy implements AnswerCreationStrategyInterface
{
    public function __construct(
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
    ) {
    }

    public function supports(GameMode $gameMode): bool
    {
        return GameMode::SuddenDeath === $gameMode;
    }

    /**
     * Checks if all QuizSessionAnswer entities for a given QuizSession are complete.
     *
     * @param QuizSession $quizSession the ID of the QuizSession to check
     *
     * @return bool true if all QuizSessionAnswer entities are complete, false otherwise
     */
    public function canCreateNewAnswer(QuizSession $quizSession): bool
    {
        return 0 === $this->quizSessionAnswerRepository->countIncorrectByQuizSessionId($quizSession->getId());
    }

    public function getViolationMessage(QuizSession $quizSession): string
    {
        return 'Une réponse incorrecte a été donnée. La session Mort Subite est terminée.';
    }
}
