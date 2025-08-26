<?php

declare(strict_types=1);

namespace App\Quiz\Validator;

use App\DTO\QuizConfigurationDTO;
use App\Quiz\Exception\QuizValidationException;
use App\Quiz\Service\QuestionCounterService;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class QuizConfigurationValidator
{
    public function __construct(
        private ValidatorInterface $validator,
        private QuestionCounterService $questionCounterService,
    ) {
    }

    /**
     * Valid all constraints : DTO and business.
     *
     * @throws QuizValidationException
     */
    public function validate(QuizConfigurationDTO $dto): void
    {
        // Validate basis constraints DTO
        $this->validateDtoConstraints($dto);

        // Validate business constraints
        $this->validateGameModeRules($dto);
        $this->validateAvailableQuestions($dto);
    }

    /**
     * Valid base constraints from DTO.
     *
     * @throws QuizValidationException
     */
    public function validateDtoConstraints(QuizConfigurationDTO $dto): void
    {
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = $violation->getMessage();
            }
            throw new QuizValidationException(implode(', ', $messages));
        }
    }

    /**
     * Check rules related to GameMode and Difficulties.
     *
     * @throws QuizValidationException
     */
    public function validateGameModeRules(QuizConfigurationDTO $dto): void
    {
        $difficultiesCount = $dto->getDifficultiesCount();

        // Vérifier si une difficulté est requise
        if ($dto->gameMode->isDifficultyRequired() && 0 === $difficultiesCount) {
            throw new QuizValidationException(
                sprintf(
                    'Une difficulté doit être sélectionnée pour le mode "%s".',
                    $dto->gameMode->getLabel()
                )
            );
        }

        // Vérifier si le mode permet plusieurs difficultés
        if (!$dto->gameMode->allowMultipleDifficulties() && $difficultiesCount > 1) {
            throw new QuizValidationException(
                sprintf(
                    'Le mode "%s" ne permet la sélection que d\'une seule difficulté.',
                    $dto->gameMode->getLabel()
                )
            );
        }
    }

    /**
     * Check if number of questions is enough.
     *
     * @throws QuizValidationException
     */
    public function validateAvailableQuestions(QuizConfigurationDTO $dto, int $minimumRequired = 20): void
    {
        // Create array of id for argument
        $difficultyIds = array_map(fn ($d) => $d->getId(), $dto->difficulties ?? []);

        $hasMinimumQuestions = $this->questionCounterService->hasMinimumQuestions(
            $minimumRequired,
            $dto->category,
            $dto->subCategory,
            $difficultyIds
        );

        if (!$hasMinimumQuestions) {
            $availableQuestions = $this->questionCounterService->countAvailableQuestions(
                $dto->category,
                $dto->subCategory,
                $difficultyIds
            );

            throw new QuizValidationException(
                sprintf(
                    'Pas assez de questions disponibles (%d). Minimum requis : %d.',
                    $availableQuestions,
                    $minimumRequired
                )
            );
        }
    }
}
