<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\QuestionRepository;
use App\Repository\QuizSessionAnswerRepository;

/**
 * Service de génération de rapports détaillés sur les questions.
 */
readonly class QuestionReportService
{
    public function __construct(
        private QuestionRepository $questionRepository,
        private QuizSessionAnswerRepository $answerRepository,
    ) {
    }

    /**
     * Génère un rapport complet sur les questions.
     *
     * @return array{
     *     overview: array{
     *         totalQuestions: int,
     *         answeredQuestions: int,
     *         unusedQuestions: int,
     *         averageSuccessRate: float,
     *         averageResponseTime: float,
     *         usageRate: float
     *     },
     *     topQuestions: array{
     *         easiest: array<int, array{entity: object, totalAnswers: int, correctAnswers: int, successRate: float}>,
     *         hardest: array<int, array{
     *              entity: object,
     *              categoryName: string,
     *              totalAnswers: int,
     *              wrongAnswers: int,
     *              avgResponseTime: float,
     *              failureRate: float
     * }>
     *     },
     *     problematicQuestions: array{
     *         slowResponse: array<int, array{entity: object, avgTime: float}>,
     *         fewAnswers: array<int, array{entity: object, totalAnswers: int}>,
     *         tooEasy: array<int, array{entity: object, failureRate: float, totalAnswers: int}>,
     *         tooDifficult: array<int, array{entity: object, failureRate: float, totalAnswers: int}>
     *     },
     *     unusedQuestions: array<int, object>,
     *     categoryAnalysis: array<int, array{
     *         categoryId: int,
     *         categoryName: string,
     *         totalQuestions: int,
     *         answeredQuestions: int,
     *         avgSuccessRate: float,
     *         avgResponseTime: float
     *     }>,
     *     difficultyAnalysis: array<int, array{
     *              name: string,
     *              color: string,
     *              totalAnswers: int,
     *              successRate: float,
     *              avgTime: float
     * }>,
     *     recommendations: array<int, array{type: string, priority: string, message: string, count: int}>,
     *     generatedAt: \DateTime
     * }
     */
    public function generateCompleteReport(): array
    {
        return [
            'overview'             => $this->getOverview(),
            'topQuestions'         => $this->getTopQuestions(),
            'problematicQuestions' => $this->getProblematicQuestions(),
            'unusedQuestions'      => $this->getUnusedQuestions(),
            'categoryAnalysis'     => $this->getCategoryAnalysis(),
            'difficultyAnalysis'   => $this->getDifficultyAnalysis(),
            'recommendations'      => $this->getRecommendations(),
            'generatedAt'          => new \DateTime(),
        ];
    }

    /**
     * Vue d'ensemble des questions.
     *
     * @return array{
     *     totalQuestions: int,
     *     answeredQuestions: int,
     *     unusedQuestions: int,
     *     averageSuccessRate: float,
     *     averageResponseTime: float,
     *     usageRate: float
     * }
     */
    private function getOverview(): array
    {
        $totalQuestions      = $this->questionRepository->count(['deletedAt' => null]);
        $answeredQuestions   = $this->questionRepository->getAnsweredQuestionsCount();
        $averageSuccessRate  = $this->answerRepository->getGlobalSuccessRate();
        $averageResponseTime = $this->answerRepository->getAverageResponseTime();

        return [
            'totalQuestions'      => $totalQuestions,
            'answeredQuestions'   => $answeredQuestions,
            'unusedQuestions'     => $totalQuestions - $answeredQuestions,
            'averageSuccessRate'  => round($averageSuccessRate, 2),
            'averageResponseTime' => round($averageResponseTime, 2), // en secondes
            'usageRate'           => $totalQuestions > 0 ? round(($answeredQuestions / $totalQuestions) * 100, 2) : 0,
        ];
    }

    /**
     * Top questions (plus faciles et plus difficiles).
     *
     * @return array{
     *     easiest: array<int, array{entity: object, totalAnswers: int, correctAnswers: int, successRate: float}>,
     *     hardest: array<int, array{
     *          entity: object,
     *          categoryName: string,
     *          totalAnswers: int,
     *          wrongAnswers: int,
     *          avgResponseTime: float,
     *          failureRate: float}
     *     >
     * }
     */
    private function getTopQuestions(): array
    {
        return [
            'easiest' => $this->questionRepository->getEasiestQuestions(10),
            'hardest' => $this->questionRepository->getHardestQuestions(10),
        ];
    }

    /**
     * Questions problématiques (temps anormaux, trop peu de réponses, etc.).
     *
     * @return array{
     *     slowResponse: array<int, array{entity: object, avgTime: float}>,
     *     fewAnswers: array<int, array{entity: object, totalAnswers: int}>,
     *     tooEasy: array<int, array{entity: object, failureRate: float, totalAnswers: int}>,
     *     tooDifficult: array<int, array{entity: object, failureRate: float, totalAnswers: int}>
     * }
     */
    private function getProblematicQuestions(): array
    {
        return [
            'slowResponse' => $this->questionRepository->getQuestionsWithSlowResponses(),
            'fewAnswers'   => $this->questionRepository->getQuestionsWithFewAnswers(5),
            'tooEasy'      => $this->questionRepository->getQuestionsTooEasy(10),
            'tooDifficult' => $this->questionRepository->getQuestionsTooDifficult(90),
        ];
    }

    /**
     * Questions jamais utilisées.
     *
     * @return array<int, object> Tableau d'entités Question
     */
    private function getUnusedQuestions(): array
    {
        return $this->questionRepository->getUnusedQuestions();
    }

    /**
     * Analyse par catégorie.
     *
     * @return array<int, array{
     *     categoryId: int,
     *     categoryName: string,
     *     totalQuestions: int,
     *     answeredQuestions: int,
     *     avgSuccessRate: float,
     *     avgResponseTime: float
     * }>
     */
    private function getCategoryAnalysis(): array
    {
        return $this->questionRepository->getStatisticsByCategory();
    }

    /**
     * Analyse par difficulté.
     *
     * @return array<int, array{name: string, color: string, totalAnswers: int, successRate: float, avgTime: float}>
     */
    private function getDifficultyAnalysis(): array
    {
        return $this->answerRepository->getPerformanceByDifficulty();
    }

    /**
     * Génère des recommandations basées sur les données.
     *
     * @return array<int, array{type: string, priority: string, message: string, count: int}>
     */
    private function getRecommendations(): array
    {
        $recommendations = [];
        $overview        = $this->getOverview();

        // Recommandation 1 : Questions inutilisées
        if ($overview['unusedQuestions'] > 0) {
            $recommendations[] = [
                'type'     => 'unused',
                'priority' => 'medium',
                'message'  => sprintf(
                    '%d question(s) ne sont jamais utilisées. Envisagez de les réviser ou de les supprimer.',
                    $overview['unusedQuestions']
                ),
                'count' => $overview['unusedQuestions'],
            ];
        }

        // Recommandation 2 : Taux de réussite global faible
        if ($overview['averageSuccessRate'] < 50) {
            $recommendations[] = [
                'type'     => 'difficulty',
                'priority' => 'high',
                'message'  => sprintf(
                    'Le taux de réussite global est de %.1f%%, ce qui est faible.
                     Envisagez de simplifier certaines questions.',
                    $overview['averageSuccessRate']
                ),
                'count' => 0,
            ];
        }

        // Recommandation 3 : Questions avec temps de réponse lent
        $slowQuestions = $this->questionRepository->getQuestionsWithSlowResponses();
        if (count($slowQuestions) > 0) {
            $recommendations[] = [
                'type'     => 'response_time',
                'priority' => 'medium',
                'message'  => sprintf(
                    '%d question(s) ont un temps de réponse moyen > 10s. Vérifiez leur clarté.',
                    count($slowQuestions)
                ),
                'count' => count($slowQuestions),
            ];
        }

        // Recommandation 4 : Questions avec peu de réponses
        $fewAnswers = $this->questionRepository->getQuestionsWithFewAnswers(5);
        if (count($fewAnswers) > 0) {
            $recommendations[] = [
                'type'     => 'low_usage',
                'priority' => 'low',
                'message'  => sprintf(
                    '%d question(s) ont moins de 5 réponses. Les données statistiques sont peu fiables.',
                    count($fewAnswers)
                ),
                'count' => count($fewAnswers),
            ];
        }

        return $recommendations;
    }
}
