<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Enum\QuizSessionStatus;
use App\Quiz\Service\QuizSessionService;
use App\Quiz\Service\QuizStatisticsService;
use App\Repository\QuizSessionAnswerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/quiz-result-v2/{id}',
    name: 'app_quiz_results_v5',
    methods: ['GET']
)]
class QuizResult2Controller extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        QuizSessionService $quizService,
        QuizSessionAnswerRepository $quizSessionAnswerRepository,
        QuizStatisticsService $quizStatisticsService,
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

        $answers = $quizSessionAnswerRepository->getQuizResults($quizSession->getId());
        // Calculer des statistiques
        $totalAnswers   = count($answers);
        $correctAnswers = array_filter($answers, fn ($answer) => $answer->isCorrect());
        $correctCount   = count($correctAnswers);
        $accuracy       = $totalAnswers > 0 ? round(($correctCount / $totalAnswers) * 100, 1) : 0;
        // Temps moyen de réponse
        $totalTime   = array_sum(array_map(fn ($answer) => $answer->getTime(), $answers));
        $averageTime = $totalAnswers > 0 ? round($totalTime / $totalAnswers, 1) : 0;

        // Calcul de la durée totale du quiz
        $interval     = $quizSession->getStartedAt()->diff($quizSession->getFinishedAt());
        $quizDuration = $interval->i * 60 + $interval->s; // en secondes

        // Calculer les scores par difficulté
        $difficultyScores = $quizStatisticsService->getScoresByDifficulty($quizSession);

        return $this->render('quiz/quiz-result_v2.html.twig', [
            'quizSession'      => $quizSession,
            'answers'          => $answers,
            'difficultyScores' => $difficultyScores,
            'statistics'       => [
                'totalAnswers' => $totalAnswers,
                'correctCount' => $correctCount,
                'accuracy'     => $accuracy,
                'averageTime'  => $averageTime,
                'quizDuration' => $quizDuration,
            ],
        ]);
    }
}
