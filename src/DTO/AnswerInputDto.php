<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AnswerInputDto
{
    #[Assert\NotNull]
    #[Assert\Type('integer')]
    public ?int $questionId = null;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    public ?int $proposalId = null;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public ?int $askedAtTimestamp = null;
}
