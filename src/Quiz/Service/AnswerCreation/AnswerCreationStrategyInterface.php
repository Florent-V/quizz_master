<?php

declare(strict_types=1);

namespace App\Quiz\Service\AnswerCreation;

use App\Entity\QuizSession;
use App\Enum\GameMode;

/**
 * Defines the contract for checking if a new answer can be created for a quiz session.
 * Each implementation will contain the logic specific to a game mode.
 */
interface AnswerCreationStrategyInterface
{
    /**
     * Checks if this policy supports the given game mode.
     */
    public function supports(GameMode $gameMode): bool;

    /**
     * Check if a new answer can be created regarding game mode rules.
     */
    public function canCreateNewAnswer(QuizSession $quizSession): bool;

    /**
     * Return error message if creation not authorized.
     */
    public function getViolationMessage(QuizSession $quizSession): string;
}
