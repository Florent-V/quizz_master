<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class SubmitAnswerInputDto
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Type('integer')]
        #[Assert\Positive]
        public int $quizSessionAnswerId,
        #[Assert\NotNull]
        #[Assert\Type('integer')]
        #[Assert\Positive]
        public int $questionId,
        #[Assert\NotNull]
        #[Assert\Type('integer')]
        #[Assert\Positive]
        public int $proposalId,
    ) {
    }
}
