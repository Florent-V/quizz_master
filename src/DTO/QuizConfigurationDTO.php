<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\GameMode;
use Symfony\Component\Validator\Constraints as Assert;

class QuizConfigurationDTO implements ValidatableQuizDTOInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le mode de jeu est obligatoire.')]
        public GameMode $gameMode,
        #[Assert\NotBlank(message: 'Veuillez saisir un pseudo.')]
        #[Assert\Length(
            min: 3,
            max: 20,
            minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractères.',
            maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères.',
        )]
        public string $pseudo,
        #[Assert\Type(type: 'integer', message: 'L\'ID de la catégorie doit être un entier.')]
        public ?int $categoryId = null,
        #[Assert\Type(type: 'integer', message: 'L\'ID de la sous-catégorie doit être un entier.')]
        public ?int $subCategoryId = null,
        /**
         * @var int[]|null
         */
        public ?array $difficultyIds = null,
    ) {
    }

    /**
     * @return int[]
     */
    public function getDifficultyIds(): array
    {
        return $this->difficultyIds ?? [];
    }

    /**
     * Return number of difficulties selected.
     */
    public function getDifficultiesCount(): int
    {
        return count($this->getDifficultyIds());
    }

    public function getGameMode(): GameMode
    {
        return $this->gameMode;
    }
}
