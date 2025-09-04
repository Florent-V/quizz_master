<?php

declare(strict_types=1);

namespace App\DTO;

readonly class CreateAnswerOutputDto
{
    public function __construct(
        public readonly int $quizSessionAnswerId,
        public readonly int $questionId,
    ) {
    }
}
