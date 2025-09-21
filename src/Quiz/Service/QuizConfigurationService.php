<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\DTO\HydratedQuizConfigurationDTO;
use App\DTO\QuizConfigurationDTO;
use App\Entity\Category;
use App\Enum\GameMode;
use App\Quiz\Exception\QuizUnprocessable;
use App\Quiz\Validator\QuizConfigurationValidator;
use App\Repository\CategoryRepository;
use App\Repository\DifficultyRepository;

final readonly class QuizConfigurationService
{
    public function __construct(
        private DifficultyRepository $difficultyRepository,
        private QuizConfigurationValidator $configurationValidator,
        private CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * Crée et valide un DTO à partir des données du LiveComponent.
     *
     * @param int[] $difficultyIds
     *
     * @throws QuizUnprocessable
     */
    public function createValidatedDto(
        ?Category $category,
        ?Category $subCategory,
        array $difficultyIds,
        ?GameMode $gameMode,
        ?string $pseudo,
    ): QuizConfigurationDTO {

        $this->configurationValidator->validateAvailableQuestions($category, $subCategory, $difficultyIds);
        $dto = $this->createDto($category, $subCategory, $difficultyIds, $gameMode, $pseudo);

        // Validation complète via le validateur métier
        $this->configurationValidator->validate($dto);

        return $dto;
    }

    public function buildHydratedDto(QuizConfigurationDTO $dto): HydratedQuizConfigurationDTO
    {
        // Chargement optimisé des entités
        $category = $dto->categoryId ?
            $this->categoryRepository->find($dto->categoryId) : null;

        $subCategory = $dto->subCategoryId ?
            $this->categoryRepository->find($dto->subCategoryId) : null;

        $difficultyIds = $dto->getDifficultyIds();
        $difficulties  = [];
        if (!empty($dto->difficultyIds)) {
            $difficulties = $this->difficultyRepository->findBy([
                'id' => $difficultyIds,
            ]);
        }

        $this->configurationValidator->validateAvailableQuestions($category, $subCategory, $difficultyIds);
        $dto = new HydratedQuizConfigurationDTO(
            gameMode: $dto->gameMode,
            pseudo: $dto->pseudo,
            difficulties: $difficulties,
            category: $category,
            subCategory: $subCategory,
        );
        $this->configurationValidator->validate($dto);

        return $dto;
    }

    /**
     * Hydrate DTO from provided data.
     *
     * @param int[] $difficultyIds
     */
    private function createDto(
        ?Category $category,
        ?Category $subCategory,
        array $difficultyIds,
        ?GameMode $gameMode,
        ?string $pseudo,
    ): QuizConfigurationDTO {

        return new QuizConfigurationDTO(
            gameMode: $gameMode,
            pseudo: $pseudo ?? 'John Doe',
            categoryId: $category?->getId(),
            subCategoryId: $subCategory?->getId(),
            difficultyIds: $difficultyIds,
        );
    }
}
