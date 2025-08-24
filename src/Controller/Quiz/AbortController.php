<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Entity\User;
use App\Enum\QuizSessionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/{id}/abort',
    name: 'app_quiz_abort',
    methods: ['POST']
)]
class AbortController extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        EntityManagerInterface $entityManager,
    ): Response {
        // Security check: only the user who started the quiz can abort it.
        // Guests (user is null) can abort their own quizzes.
        if ($quizSession->getUser() !== $this->getUser()) {
            return $this->json(
                [
                    'error' => 'Vous n\'êtes pas autorisé à abandonner ce quiz.',
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        // Prevent aborting a quiz that is already finished or aborted.
        if (in_array($quizSession->getStatus(), [QuizSessionStatus::Finished, QuizSessionStatus::Cancelled])) {
            return $this->json(['message' => 'Ce quiz est déjà terminé ou abandonné.'], Response::HTTP_OK);
        }

        $quizSession->setStatus(QuizSessionStatus::Cancelled);
        $quizSession->setFinishedAt(new \DateTime());
        $entityManager->flush();

        return $this->json(['message' => 'Quiz abandonné avec succès.'], Response::HTTP_OK);
    }
}
