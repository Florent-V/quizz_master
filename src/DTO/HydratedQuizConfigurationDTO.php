<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Category;
use App\Entity\Difficulty;
use App\Enum\GameMode;
use Symfony\Component\Validator\Constraints as Assert;

class HydratedQuizConfigurationDTO implements ValidatableQuizDTOInterface
{
    /**
     * @param Difficulty[] $difficulties
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Le mode de jeu est obligatoire.')]
        public GameMode $gameMode,
        #[Assert\NotBlank(message: 'Le pseudo ne peut pas être vide.')]
        #[Assert\Length(
            min: 3,
            max: 20,
            minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractères.',
            maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères.',
        )]
        public string $pseudo,
        /** @var Difficulty[] */
        #[Assert\Count(
            min: 0,
            minMessage: 'Au moins une difficulté doit être sélectionnée.',
        )]
        #[Assert\NotNull(message: 'Le pseudo ne peut pas être vide.')]
        public array $difficulties,
        #[Assert\Expression(
            expression: 'this.getSubCategory() === null or this.getCategory() !== null',
            message: 'La catégorie est obligatoire si une sous-catégorie est fournie.'
        )]
        public ?Category $category,
        #[Assert\Expression(
            expression: 'this.getCategory() === null ' .
            'or this.getSubCategory() === null ' .
            'or this.getSubCategory().getParent() === this.getCategory()',
            message: 'La sous-catégorie doit appartenir à la catégorie parente.'
        )]
        public ?Category $subCategory,
    ) {
    }

    // === MÉTHODES D'AFFICHAGE ===
    public function getCategoryName(): string
    {
        return $this->category?->getName() ?? 'Toutes les catégories';
    }

    public function getSubCategoryName(): string
    {
        return $this->subCategory?->getName() ?? 'Toutes les sous-catégories';
    }

    public function getDifficultiesLabel(): string
    {
        if (empty($this->difficulties)) {
            return 'Toutes les difficultés';
        }

        return implode(', ', array_map(
            fn (Difficulty $d) => $d->getName(),
            $this->difficulties
        ));
    }

    /** @return string[] */
    public function getDifficultyNames(): array
    {
        return array_map(fn (Difficulty $d) => $d->getName(), $this->difficulties);
    }

    public function getGameModeLabel(): string
    {
        return $this->gameMode->getLabel();
    }

    // === MÉTHODES UTILITAIRES ===
    public function getDifficultiesCount(): int
    {
        return count($this->difficulties);
    }

    public function getSubCategory(): ?Category
    {
        return $this->subCategory;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function hasCategory(): bool
    {
        return null !== $this->category;
    }

    public function hasSubCategory(): bool
    {
        return null !== $this->subCategory;
    }

    public function hasDifficulties(): bool
    {
        return !empty($this->difficulties);
    }

    /**
     * Returns a summary of the quiz configuration as an associative array.
     *
     * @return array{
     *     category: string|null,
     *     subCategory: string|null,
     *     difficulties: string,
     *     gameMode: string,
     *     pseudo: string
     * }
     */
    public function getSummary(): array
    {
        return [
            'category'     => $this->getCategoryName(),
            'subCategory'  => $this->getSubCategoryName(),
            'difficulties' => $this->getDifficultiesLabel(),
            'gameMode'     => $this->getGameModeLabel(),
            'pseudo'       => $this->pseudo,
        ];
    }

    /**
     * @return int[]
     */
    public function getDifficultyIds(): array
    {
        return array_map(
            fn (Difficulty $difficulty) => $difficulty->getId(),
            $this->difficulties
        );
    }

    public function getGameMode(): GameMode
    {
        return $this->gameMode;
    }
}
