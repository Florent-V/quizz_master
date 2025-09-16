<?php

declare(strict_types=1);

namespace App\Quiz\Service\FinishQuiz;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Repository\QuizSessionAnswerRepository;

readonly class TimeAttackFinishStrategy implements FinishQuizStrategyInterface
{
    public function __construct(
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
    ) {
    }

    public function supports(GameMode $gameMode): bool
    {
        return GameMode::TimeAttack === $gameMode;
    }

    public function canFinishQuiz(QuizSession $quizSession): bool
    {
        $baseTimeLimit  = GameMode::TimeAttack->getTimeLimit();
        $correctAnswers = $this->quizSessionAnswerRepository->countCorrectByQuizSessionId($quizSession->getId());
        $bonusTime      = $correctAnswers * GameMode::TimeAttack->getBonusTimePerGoodAnswer();
        $totalTimeLimit = $baseTimeLimit + $bonusTime;
        $elapsedTime    = $this->getElapsedTimeInSeconds($quizSession);

        return $elapsedTime >= $totalTimeLimit;
    }

    public function getViolationMessage(QuizSession $quizSession): string
    {
        return 'Le temps imparti pour le mode Contre-la-montre n\'est pas encore écoulé.';
    }

    private function getElapsedTimeInSeconds(QuizSession $quizSession): int
    {
        $now       = new \DateTime();
        $startTime = $quizSession->getStartedAt();

        if (!$startTime) {
            return 0;
        }

        return $now->getTimestamp() - $startTime->getTimestamp();
    }
}
