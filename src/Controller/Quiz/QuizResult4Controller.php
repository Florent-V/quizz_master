<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Enum\QuizSessionStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/quiz-result-v4/{id}',
    name: 'app_quiz_results_v1',
    methods: ['GET']
)]
class QuizResult4Controller extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
    ): Response {
        // Security checks
        if ($this->getUser() !== $quizSession->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à voir ces résultats.');

            return $this->redirectToRoute('app_home');
        }

        // Ensure the quiz has been completed
        if (QuizSessionStatus::Finished !== $quizSession->getStatus()) {
            $this->addFlash('warning', 'Ce quiz n\'est pas encore terminé.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('quiz/quiz-result_v4.html.twig', [
            'quizSession' => $quizSession,
        ]);
    }
}
