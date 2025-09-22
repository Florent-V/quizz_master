<?php

declare(strict_types=1);

namespace App\DTO;

readonly class SubmitAnswerOutputDto
{
    public function __construct(
        public readonly int $quizSessionAnswerId,
        public readonly int $goodAnswerId,
        public readonly bool $isCorrect,
        public readonly int $timeSpent,
        public readonly int $totalScore,
        public readonly int $answerScore,
    ) {
    }
}
