<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Enum\QuizSessionStatus;
use App\Quiz\Service\QuizService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/results-v3/{id}',
    name: 'app_quiz_results_v3',
    methods: ['GET']
)]
class Result3Controller extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        QuizService $quizService,
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
        $statistics = $quizService->calculateQuizStatistics($quizSession);

        return $this->render('quiz/result_v3.html.twig', array_merge([
            'quizSession' => $quizSession,
            'answers'     => $quizSession->getQuizSessionAnswers(),
        ], $statistics));
    }
}
