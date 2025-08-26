<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Category;
use App\Entity\Difficulty;
use App\Enum\GameMode;
use Symfony\Component\Validator\Constraints as Assert;

class QuizConfigurationDTO
{
    public ?Category $category = null;

    public ?Category $subCategory = null;

    /**
     * @var Difficulty[]|null
     */
    public ?array $difficulties = null;

    #[Assert\NotBlank(message: 'Le mode de jeu est obligatoire.')]
    public GameMode $gameMode;

    #[Assert\NotBlank(message: 'Veuillez saisir un pseudo.')]
    #[Assert\Length(
        min: 3,
        max: 20,
        minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères.',
    )]
    public string $pseudo;

    /**
     * @return int[]
     */
    public function getDifficultyIds(): array
    {
        return array_map(
            fn (Difficulty $difficulty) => $difficulty->getId(),
            $this->difficulties ?? []
        );
    }

    /**
     * Return number of difficulties selected.
     */
    public function getDifficultiesCount(): int
    {
        return count($this->difficulties ?? []);
    }
}
