<?php

declare(strict_types=1);

namespace App\Quiz\Service\FinishQuiz;

use App\Entity\QuizSession;
use App\Quiz\Exception\GameModeViolationException;
use App\Quiz\Exception\QuizSessionException;

readonly class FinishQuizValidationService
{
    public function __construct(
        private FinishQuizStrategyRegistry $strategyRegistry,
    ) {
    }

    /**
     * @throws GameModeViolationException|QuizSessionException
     */
    public function validateCanFinishQuiz(QuizSession $quizSession): void
    {
        $gameMode = $quizSession->getGameMode();

        if (!$gameMode) {
            throw new QuizSessionException('Quiz session must have a game mode.');
        }

        $strategy = $this->strategyRegistry->getStrategy($gameMode);

        if (!$strategy->canFinishQuiz($quizSession)) {
            throw new GameModeViolationException(
                $strategy->getViolationMessage($quizSession)
            );
        }
    }
}
