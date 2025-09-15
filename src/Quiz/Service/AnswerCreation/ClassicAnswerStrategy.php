<?php

declare(strict_types=1);

namespace App\Quiz\Service\AnswerCreation;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Repository\QuizSessionAnswerRepository;

readonly class ClassicAnswerStrategy implements AnswerCreationStrategyInterface
{
    public function __construct(
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
    ) {
    }

    public function supports(GameMode $gameMode): bool
    {
        return GameMode::TwentyQuestions === $gameMode;
    }

    public function canCreateNewAnswer(QuizSession $quizSession): bool
    {
        return $this->quizSessionAnswerRepository->countByQuizSessionId($quizSession->getId())
            < GameMode::TwentyQuestions->getQuestionLimit();
    }

    public function getViolationMessage(QuizSession $quizSession): string
    {
        return sprintf(
            'Limite de %d questions atteinte pour le mode 20 Questions.',
            GameMode::TwentyQuestions->getQuestionLimit()
        );
    }
}
