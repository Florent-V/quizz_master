<?php

declare(strict_types=1);

namespace App\Quiz\Service\FinishQuiz;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Repository\QuizSessionAnswerRepository;

readonly class SuddenDeathFinishStrategy implements FinishQuizStrategyInterface
{
    public function __construct(
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
    ) {
    }

    public function supports(GameMode $gameMode): bool
    {
        return GameMode::SuddenDeath === $gameMode;
    }

    public function canFinishQuiz(QuizSession $quizSession): bool
    {
        $incorrectCount = $this->quizSessionAnswerRepository->countIncorrectByQuizSessionId($quizSession->getId());
        if (1 !== $incorrectCount) {
            return false;
        }

        $lastAnswer = $this->quizSessionAnswerRepository->findLastAnswer($quizSession->getId());

        // The session can only end if the very last action was a wrong answer.
        return $lastAnswer && !$lastAnswer->isCorrect();
    }

    public function getViolationMessage(QuizSession $quizSession): string
    {
        return 'Une session en Mort Subite ne peut se terminer que sur une unique et dernière réponse incorrecte.';
    }
}
