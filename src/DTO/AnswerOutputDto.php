<?php

declare(strict_types=1);

namespace App\DTO;

final class AnswerOutputDto
{
    public function __construct(
        public bool $isCorrect,
        public int $correctProposalId,
        public int $score,
    ) {
    }
}
