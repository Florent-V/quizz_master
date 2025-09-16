<?php

declare(strict_types=1);

namespace App\Quiz\Service\AnswerCreation;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Repository\QuizSessionAnswerRepository;

readonly class SpeedRunAnswerStrategy implements AnswerCreationStrategyInterface
{
    public function __construct(
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
    ) {
    }

    public function supports(GameMode $gameMode): bool
    {
        return GameMode::SpeedRun === $gameMode;
    }

    public function canCreateNewAnswer(QuizSession $quizSession): bool
    {
        return $this->quizSessionAnswerRepository->countByQuizSessionId($quizSession->getId())
            < GameMode::SpeedRun->getQuestionLimit();
    }

    public function getViolationMessage(QuizSession $quizSession): string
    {
        return sprintf(
            'Limite de %d questions atteinte pour le mode SpeedRun.',
            GameMode::SpeedRun->getQuestionLimit()
        );
    }
}
