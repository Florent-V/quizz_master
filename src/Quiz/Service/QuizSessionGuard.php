<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\Entity\QuizSession;
use App\Entity\User;
use App\Enum\QuizSessionStatus;
use App\Quiz\Exception\QuizNotFoundException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class QuizSessionGuard
{
    public function __construct(private Security $security)
    {
    }

    /**
     * @throws QuizNotFoundException
     */
    public function guardSessionExists(?QuizSession $quizSession): void
    {
        if (null === $quizSession) {
            throw new QuizNotFoundException('Quiz session not found.');
        }
    }

    /**
     * @throws AccessDeniedException
     */
    public function guardSessionIsInProgress(QuizSession $quizSession): void
    {
        if (QuizSessionStatus::InProgress !== $quizSession->getStatus() || null !== $quizSession->getFinishedAt()) {
            throw new AccessDeniedException('Quiz session is Over.');
        }
    }

    /**
     * @throws AccessDeniedException
     */
    public function guardUserOwnsSession(QuizSession $quizSession, ?User $user = null): void
    {
        if (null === $user) {
            /** @var ?User $user */
            $user = $this->security->getUser();
        }

        if ($user && $quizSession->getUser() !== $user) {
            throw new AccessDeniedException('You do not own this quiz session.');
        }
    }

    /**
     * @throws AccessDeniedException
     */
    public function guardSessionIsAlreadyDone(QuizSession $quizSession): void
    {
        if (in_array($quizSession->getStatus(), [QuizSessionStatus::Finished, QuizSessionStatus::Cancelled])) {
            throw new AccessDeniedException('Quiz session is already done.');
        }
    }
}
