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
    '/quiz/{id}/abort',
    name: 'app_quiz_abort',
    methods: ['GET']
)]
class AbortController extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        EntityManagerInterface $entityManager,
    ): Response {
        // Security check: only the user who started the quiz can abort it.
        if ($this->getUser() !== $quizSession->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à terminer ce quiz.');
            throw $this->createAccessDeniedException();
        }

        // Prevent aborting a quiz that is already finished or aborted.
        if (in_array($quizSession->getStatus(), [QuizSessionStatus::Finished, QuizSessionStatus::Cancelled])) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à terminer ce quiz.');
            throw $this->createAccessDeniedException();
        }

        $quizSession->setStatus(QuizSessionStatus::Cancelled);
        $quizSession->setFinishedAt(new \DateTime());
        $entityManager->flush();

        return $this->redirectToRoute('app_home');
    }
}
