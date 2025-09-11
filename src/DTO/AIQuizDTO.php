<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Difficulty;
use Symfony\Component\Validator\Constraints as Assert;

class AIQuizDTO
{
    #[Assert\NotBlank(message: 'Le thème ne peut pas être vide.')]
    #[Assert\Length(
        min: 3,
        max: 80,
        minMessage: 'Le thème doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le thème ne doit pas dépasser {{ limit }} caractères.'
    )]
    public ?string $theme = null;

    #[Assert\NotBlank(message: 'Veuillez sélectionner une difficulté.')]
    public ?Difficulty $difficulty = null;
}
