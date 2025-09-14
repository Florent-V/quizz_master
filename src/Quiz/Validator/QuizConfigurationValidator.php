<?php

declare(strict_types=1);

namespace App\Quiz\Validator;

use App\DTO\ValidatableQuizDTOInterface;
use App\Entity\Category;
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
    public function validate(ValidatableQuizDTOInterface $dto): void
    {
        // Validate basis constraints DTO
        $this->validateDtoConstraints($dto);
        // Validate business constraints
        $this->validateGameModeRules($dto);
    }

    /**
     * Valid base constraints from DTO.
     *
     * @throws QuizValidationException
     */
    public function validateDtoConstraints(ValidatableQuizDTOInterface $dto): void
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
    public function validateGameModeRules(ValidatableQuizDTOInterface $dto): void
    {
        $difficultiesCount = $dto->getDifficultiesCount();
        $gameMode          = $dto->getGameMode();

        // Vérifier si une difficulté est requise
        if ($gameMode->isDifficultyRequired() && 0 === $difficultiesCount) {
            throw new QuizValidationException(
                sprintf(
                    'Une difficulté doit être sélectionnée pour le mode "%s".',
                    $gameMode->getLabel()
                )
            );
        }

        // Vérifier si le mode permet plusieurs difficultés
        if (!$gameMode->allowMultipleDifficulties() && $difficultiesCount > 1) {
            throw new QuizValidationException(
                sprintf(
                    'Le mode "%s" ne permet la sélection que d\'une seule difficulté.',
                    $gameMode->getLabel()
                )
            );
        }
    }

    /**
     * Check if number of questions is enough.
     *
     * @param int[] $difficultyIds
     *
     * @throws QuizValidationException
     */
    public function validateAvailableQuestions(
        Category $category,
        Category $subCategory,
        array $difficultyIds,
        int $minimumRequired = 20,
    ): void {
        $availableQuestions = $this->questionCounterService->hasMinimumQuestions(
            $minimumRequired,
            $category,
            $subCategory,
            $difficultyIds
        );

        if ($availableQuestions < $minimumRequired) {
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
