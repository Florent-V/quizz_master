<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Enum\QuizSessionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/{id}/finish',
    name: 'app_quiz_finish',
    methods: ['GET']
)]
class FinishController extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        EntityManagerInterface $entityManager,
    ): Response {
        // Security check
        if ($this->getUser() !== $quizSession->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à terminer ce quiz.');

            return $this->redirectToRoute('app_home');
        }

        // Prevent re-finishing
        if (QuizSessionStatus::Finished === $quizSession->getStatus()) {
            return $this->redirectToRoute('app_quiz_results', ['id' => $quizSession->getId()]);
        }

        // TODO: Add logic to check if all questions have been answered based on game mode.
        // For now, we assume the frontend redirects only when the game is over.

        $quizSession->setStatus(QuizSessionStatus::Finished);
        $quizSession->setFinishedAt(new \DateTime());
        $entityManager->flush();

        return $this->redirectToRoute('app_quiz_results', ['id' => $quizSession->getId()]);
    }
}
