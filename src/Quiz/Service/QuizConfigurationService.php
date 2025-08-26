<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\DTO\QuizConfigurationDTO;
use App\Entity\Category;
use App\Enum\GameMode;
use App\Quiz\Exception\QuizValidationException;
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
     * @throws QuizValidationException
     */
    public function createValidatedDto(
        ?Category $category,
        ?Category $subCategory,
        array $difficultyIds,
        ?GameMode $gameMode,
        ?string $pseudo,
    ): QuizConfigurationDTO {
        $dto = $this->hydrateDto($category, $subCategory, $difficultyIds, $gameMode, $pseudo);

        // Validation complète via le validateur métier
        $this->configurationValidator->validate($dto);

        return $dto;
    }

    public function retrieveData(QuizConfigurationDTO $dto): QuizConfigurationDTO
    {
        if (null !== $dto->category) {
            $dto->category = $this->categoryRepository->find($dto->category->getId());
        }

        if (null !== $dto->subCategory) {
            $dto->subCategory = $this->categoryRepository->find($dto->subCategory->getId());
        }

        return $dto;
    }

    /**
     * Hydrate DTO from provided data.
     *
     * @param int[] $difficultyIds
     */
    private function hydrateDto(
        ?Category $category,
        ?Category $subCategory,
        array $difficultyIds,
        ?GameMode $gameMode,
        ?string $pseudo,
    ): QuizConfigurationDTO {
        $dto              = new QuizConfigurationDTO();
        $dto->category    = $category;
        $dto->subCategory = $subCategory;
        $dto->gameMode    = $gameMode;
        $dto->pseudo      = $pseudo ?? '';

        if (!empty($difficultyIds)) {
            $dto->difficulties = $this->difficultyRepository->findBy(['id' => $difficultyIds]);
        }

        return $dto;
    }
}
