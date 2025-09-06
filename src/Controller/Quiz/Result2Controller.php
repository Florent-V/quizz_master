<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Enum\QuizSessionStatus;
use App\Quiz\Service\QuizSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/results-v2/{id}',
    name: 'app_quiz_results_v2',
    methods: ['GET']
)]
class Result2Controller extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        QuizSessionService $quizService,
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

        // Calcul des statistiques
        $statistics = $quizService->getQuizStatistics($quizSession);

        return $this->render('quiz/result_v2.html.twig', [
            'quizSession' => $quizSession,
            'statistics'  => $statistics,
        ]);
    }
}
