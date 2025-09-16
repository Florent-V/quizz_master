<?php

declare(strict_types=1);

namespace App\Quiz\Service\FinishQuiz;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Repository\QuizSessionAnswerRepository;

readonly class ClassicFinishStrategy implements FinishQuizStrategyInterface
{
    public function __construct(
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
    ) {
    }

    public function supports(GameMode $gameMode): bool
    {
        return GameMode::TwentyQuestions === $gameMode;
    }

    public function canFinishQuiz(QuizSession $quizSession): bool
    {
        $questionLimit = GameMode::TwentyQuestions->getQuestionLimit();
        $answerCount   = $this->quizSessionAnswerRepository->countByQuizSessionId($quizSession->getId());

        return $answerCount === $questionLimit;
    }

    public function getViolationMessage(QuizSession $quizSession): string
    {
        return sprintf(
            'La session n\'a pas atteint les %d réponses requises pour être terminée.',
            GameMode::TwentyQuestions->getQuestionLimit()
        );
    }
}
