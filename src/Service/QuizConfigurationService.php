<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\QuizConfigurationDTO;
use App\Enum\GameMode;
use App\Repository\CategoryRepository;
use App\Repository\DifficultyRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class QuizConfigurationService
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private DifficultyRepository $difficultyRepository,
        private ValidatorInterface $validator,
    ) {
    }

    public function buildFromSession(SessionInterface $session): ?QuizConfigurationDTO
    {
        $configData = $this->extractConfigFromSession($session);
        if (!$configData) {
            return null;
        }

        $quizDto = $this->hydrateDto($configData);
        $this->validateDto($quizDto);

        return $quizDto;
    }

    /**
     * Récupère la configuration du quiz depuis la session.
     *
     * @return array{
     *     category_id: int|null,
     *     subcategory_id: int|null,
     *     difficulty_ids: int[],
     *     gameMode: string,
     *     pseudo: string
     * }|null
     */
    private function extractConfigFromSession(SessionInterface $session): ?array
    {
        $configData = $session->get('quiz_configuration');
        $session->remove('quiz_configuration');

        return $configData;
    }

    /**
     * Hydrate un QuizConfigurationDTO à partir des données de session.
     *
     * @param array{
     *     category_id: int|null,
     *     subcategory_id: int|null,
     *     difficulty_ids: int[],
     *     gameMode: string,
     *     pseudo: string
     * } $configData
     */
    private function hydrateDto(array $configData): QuizConfigurationDTO
    {
        $quizDto           = new QuizConfigurationDTO();
        $quizDto->pseudo   = $configData['pseudo'];
        $quizDto->gameMode = GameMode::tryFrom($configData['gameMode']);

        if (!empty($configData['category_id'])) {
            $quizDto->category = $this->categoryRepository->find($configData['category_id']);
        }
        if (!empty($configData['subcategory_id'])) {
            $quizDto->subCategory = $this->categoryRepository->find($configData['subcategory_id']);
        }
        if (!empty($configData['difficulty_ids'])) {
            $quizDto->difficulties = $this->difficultyRepository->findBy(['id' => $configData['difficulty_ids']]);
        }

        return $quizDto;
    }

    private function validateDto(QuizConfigurationDTO $quizDto): void
    {
        $errors = $this->validator->validate($quizDto);

        if (count($errors) > 0) {
            $messages = array_map(fn ($e) => $e->getMessage(), iterator_to_array($errors));
            throw new \DomainException(implode(', ', $messages));
        }
    }
}
