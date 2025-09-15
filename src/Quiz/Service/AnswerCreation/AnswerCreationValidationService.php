<?php

declare(strict_types=1);

namespace App\Quiz\Service\AnswerCreation;

use App\Entity\QuizSession;
use App\Quiz\Exception\AnswerException;
use App\Quiz\Exception\GameModeViolationException;
use App\Quiz\Exception\QuizSessionException;
use App\Quiz\Service\QuizSessionService;

readonly class AnswerCreationValidationService
{
    public function __construct(
        private AnswerCreationStrategyRegistry $strategyRegistry,
        private OrphanAnswerCounter $orphanAnswerCounter,
        private QuizSessionService $quizSessionService,
    ) {
    }

    /**
     * @throws GameModeViolationException|QuizSessionException|AnswerException
     */
    public function validateCanCreateAnswer(QuizSession $quizSession): void
    {
        // 1. Vérification commune : pas de réponse en attente
        $this->validateNoPendingAnswer($quizSession);

        // 2. Vérifications spécifiques au mode de jeu
        $gameMode = $quizSession->getGameMode();

        if (!$gameMode) {
            throw new QuizSessionException('Quiz session must have a game mode.');
        }

        $strategy = $this->strategyRegistry->getStrategy($gameMode);

        if (!$strategy->canCreateNewAnswer($quizSession)) {
            // Pour les violations de mode de jeu, on clôture la session
            $this->quizSessionService->finishQuizSession($quizSession);

            throw new GameModeViolationException(
                $strategy->getViolationMessage($quizSession)
            );
        }
    }

    /**
     * Vérifie qu'il n'y a pas de réponse en attente (non répondue).
     *
     * @throws AnswerException
     */
    private function validateNoPendingAnswer(QuizSession $quizSession): void
    {
        $countPendingAnswer = $this->orphanAnswerCounter->count($quizSession);

        if (0 !== $countPendingAnswer) {
            throw new AnswerException(
                'Une question est déjà en cours. Vous devez y répondre avant de pouvoir passer à la suivante.',
                409
            );
        }
    }
}
