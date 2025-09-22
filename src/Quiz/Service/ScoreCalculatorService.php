<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\Entity\QuizSessionAnswer;

class ScoreCalculatorService
{
    private const int TIME_LIMIT        = 20; // in seconds
    private const float MIN_SCORE_RATIO = 0.5; // 50% du score de base

    public function calculateScore(QuizSessionAnswer $answer): int
    {
        if (!$answer->isCorrect()) {
            return 0;
        }

        $difficulty = $answer->getQuestion()->getDifficulty();
        $baseScore  = $difficulty->getBasePoints();
        if (null === $baseScore) {
            // Fallback to a default value if not set
            $baseScore = 100;
        }

        $responseTime = $answer->getTime();
        if ($responseTime < 0) {
            // Should not happen, but as a safeguard
            return 0;
        }

        $ratio = 1 - (min($responseTime, self::TIME_LIMIT) / self::TIME_LIMIT) * (1 - self::MIN_SCORE_RATIO);

        return (int) round($baseScore * $ratio);
    }
}
