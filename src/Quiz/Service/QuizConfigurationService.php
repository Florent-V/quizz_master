<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\DTO\QuizConfigurationDTO;
use App\Enum\GameMode;
use App\Repository\CategoryRepository;
use App\Repository\DifficultyRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class QuizConfigurationService
{
    /**
     * @var array{
     *     category_id: int|null,
     *     subcategory_id: int|null,
     *     difficulty_ids: int[],
     *     gameMode: string,
     *     pseudo: string
     * }|null
     */
    private ?array $configData = null;

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly DifficultyRepository $difficultyRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Récupère la configuration depuis la session (début du chaînage).
     */
    public function fromSession(SessionInterface $session): self
    {
        $this->configData = $session->get('quiz_configuration');

        return $this;
    }

    /**
     * Nettoie la configuration de la session (optionnel dans le chaînage).
     */
    public function clearSession(SessionInterface $session): self
    {
        $session->remove('quiz_configuration');

        return $this;
    }

    /**
     * Construit le DTO à partir de la configuration extraite.
     */
    public function build(): ?QuizConfigurationDTO
    {
        if (!$this->configData) {
            return null;
        }

        $quizDto = $this->hydrateDto($this->configData);
        $this->validateDto($quizDto);

        return $quizDto;
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
