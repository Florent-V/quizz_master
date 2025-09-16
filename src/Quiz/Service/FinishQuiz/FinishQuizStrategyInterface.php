<?php

declare(strict_types=1);

namespace App\Quiz\Service\FinishQuiz;

use App\Entity\QuizSession;
use App\Enum\GameMode;

/**
 * Defines the contract for checking if a quiz session can be finished.
 * Each implementation will contain the logic specific to a game mode.
 */
interface FinishQuizStrategyInterface
{
    /**
     * Checks if this policy supports the given game mode.
     */
    public function supports(GameMode $gameMode): bool;

    /**
     * Check if a quiz session can be finished regarding game mode rules.
     */
    public function canFinishQuiz(QuizSession $quizSession): bool;

    /**
     * Return error message if finishing is not authorized.
     */
    public function getViolationMessage(QuizSession $quizSession): string;
}
