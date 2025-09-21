<?php

declare(strict_types=1);

namespace App\Quiz\Service\FinishQuiz;

use App\Entity\QuizSession;
use App\Quiz\Exception\QuizConflictException;

// use App\Quiz\Service\QuizAnswerService;

readonly class FinishQuizValidationService
{
    public function __construct(
        private FinishQuizStrategyRegistry $strategyRegistry,
        // private QuizAnswerService $quizAnswerService,
    ) {
    }

    /**
     * @throws \LogicException|QuizConflictException
     */
    public function validateCanFinishQuiz(QuizSession $quizSession): void
    {
        // @TODO find a solution because time attack ends with pending question
        // 1. Vérification commune : pas de réponse en attente
        // $this->quizAnswerService->validateNoPendingAnswer($quizSession);

        // 2. Vérifications spécifiques au mode de jeu
        $gameMode = $quizSession->getGameMode();

        if (!$gameMode) {
            throw new \LogicException('Quiz session must have a game mode.');
        }

        $strategy = $this->strategyRegistry->getStrategy($gameMode);

        if (!$strategy->canFinishQuiz($quizSession)) {
            throw new QuizConflictException(
                $strategy->getViolationMessage($quizSession)
            );
        }
    }
}
