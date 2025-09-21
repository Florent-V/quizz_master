<?php

declare(strict_types=1);

namespace App\Quiz\Service\AnswerCreation;

use App\Entity\QuizSession;
use App\Quiz\Exception\QuizConflictException;
use App\Quiz\Service\QuizAnswerService;

readonly class AnswerCreationValidationService
{
    public function __construct(
        private AnswerCreationStrategyRegistry $strategyRegistry,
        private QuizAnswerService $quizAnswerService,
    ) {
    }

    /**
     * @throws \LogicException|QuizConflictException
     */
    public function validateCanCreateAnswer(QuizSession $quizSession): void
    {
        // 1. Vérification commune : pas de réponse en attente
        $this->quizAnswerService->validateNoPendingAnswer($quizSession);

        // 2. Vérifications spécifiques au mode de jeu
        $gameMode = $quizSession->getGameMode();

        if (!$gameMode) {
            throw new \LogicException('Quiz session must have a game mode.');
        }

        $strategy = $this->strategyRegistry->getStrategy($gameMode);

        if (!$strategy->canCreateNewAnswer($quizSession)) {
            // @TODO : reflexion : pour les violations de mode de jeu, on clôture la session
            // $this->quizSessionService->failQuizSession($quizSession);

            throw new QuizConflictException(
                $strategy->getViolationMessage($quizSession)
            );
        }
    }
}
