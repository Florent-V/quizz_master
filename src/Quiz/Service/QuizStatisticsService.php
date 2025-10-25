<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\Entity\Question;
use App\Entity\QuizSession;
use App\Entity\QuizSessionAnswer;
use App\Enum\GameMode;
use App\Repository\CategoryRepository;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionAnswerRepository;
use App\Repository\QuizSessionRepository;

/**
 * Service for calculating and retrieving quiz-related statistics.
 */
readonly class QuizStatisticsService
{
    public function __construct(
        private QuizSessionRepository $sessionRepository,
        private QuizSessionAnswerRepository $answerRepository,
        private QuestionRepository $questionRepository,
        private CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * Retrieves statistics for a specific game mode.
     *
     * @param GameMode $gameMode The game mode
     *
     * @return array{
     *     averageScore: float,
     *     bestScore: int
     * }
     */
    public function getGameModeStatisticsForMode(GameMode $gameMode): array
    {
        return [
            'averageScore' => $this->sessionRepository->getAverageScoreByGameMode($gameMode),
            'bestScore'    => $this->sessionRepository->getBestScoreByGameMode($gameMode),
        ];
    }

    /**
     * Retrieves statistics for a specific quiz session.
     *
     * @param QuizSession $session The quiz session
     *
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     *
     * @return array{
     *     session: QuizSession,
     *     totalAnswers: int,
     *     correctAnswers: int,
     *     wrongAnswers: int,
     *     successRate: float,
     *     totalTime: int,
     *     averageTime: int,
     *     fastestAnswer: int,
     *     slowestAnswer: int,
     *     categoryStats: array<string, array{total: int, correct: int, percentage: float}>,
     *     duration: ?int,
     *     rank: int
     * }
     */
    public function getSessionStatistics(QuizSession $session): array
    {
        $answers = $session->getQuizSessionAnswers();

        $totalAnswers   = $answers->count();
        $correctAnswers = $answers->filter(fn ($answer) => $answer->isCorrect())->count();
        $totalTime      = 0;
        $fastestAnswer  = PHP_INT_MAX;
        $slowestAnswer  = 0;
        $categoryStats  = [];

        foreach ($answers as $answer) {
            if ($answer->getTime()) {
                $totalTime += $answer->getTime();
                $fastestAnswer = min($fastestAnswer, $answer->getTime());
                $slowestAnswer = max($slowestAnswer, $answer->getTime());
            }

            // Stats par catégorie
            $category = $answer->getQuestion()?->getCategory();
            if ($category) {
                $categoryName = $category->getName();
                if (!isset($categoryStats[$categoryName])) {
                    $categoryStats[$categoryName] = [
                        'total'      => 0,
                        'correct'    => 0,
                        'percentage' => 0,
                    ];
                }
                ++$categoryStats[$categoryName]['total'];
                if ($answer->isCorrect()) {
                    ++$categoryStats[$categoryName]['correct'];
                }
            }
        }

        // Calcul des pourcentages par catégorie
        foreach ($categoryStats as $category => $stats) {
            $categoryStats[$category]['percentage'] = round(
                ($stats['correct'] / $stats['total']) * 100,
                1
            );
        }

        $avgTime     = $totalAnswers > 0 && $totalTime > 0 ? round($totalTime / $totalAnswers) : 0;
        $successRate = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 1) : 0;

        return [
            'session'        => $session,
            'totalAnswers'   => $totalAnswers,
            'correctAnswers' => $correctAnswers,
            'wrongAnswers'   => $totalAnswers - $correctAnswers,
            'successRate'    => $successRate,
            'totalTime'      => $totalTime,
            'averageTime'    => $avgTime,
            'fastestAnswer'  => PHP_INT_MAX === $fastestAnswer ? 0 : $fastestAnswer,
            'slowestAnswer'  => $slowestAnswer,
            'categoryStats'  => $categoryStats,
            'duration'       => $this->calculateSessionDuration($session),
            'rank'           => $this->getSessionRank($session),
        ];
    }

    /**
     * Retrieves global application statistics.
     *
     * @return array<string, mixed>
     */
    public function getGlobalStatistics(): array
    {
        // Sessions
        $totalSessions      = $this->sessionRepository->count([]);
        $completedSessions  = $this->sessionRepository->count(['status' => 'FINISHED']);
        $inProgressSessions = $this->sessionRepository->count(['status' => 'IN_PROGRESS']);

        // Scores
        $avgScore     = $this->sessionRepository->getAverageScore();
        $highestScore = $this->sessionRepository->getHighestScore();
        $topSessions  = $this->sessionRepository->getTopSessions(10);

        // Réponses
        $totalAnswers      = $this->answerRepository->count([]);
        $correctAnswers    = $this->answerRepository->count(['isCorrect' => true]);
        $globalSuccessRate = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 1) : 0;

        // Temps de réponse
        $avgResponseTime = $this->answerRepository->getAverageResponseTime();

        // Stats par mode de jeu
        $gameModeStats = $this->getGameModeStatistics();

        // Stats par catégorie
        $categoryStats = $this->getCategoryStatistics();

        // Questions les plus difficiles
        $hardestQuestions = $this->getHardestQuestions(10);

        // Tendances temporelles
        $dailyStats = $this->getDailyStatistics(30);

        //        array{
        //            sessions: array{total: int, completed: int, inProgress: int, completionRate: float},
        //            scores: array{average: float, highest: int, topSessions: QuizSession[]},
        //            answers: array{total: int, correct: int, successRate: float, avgResponseTime: int},
        //            gameModes: array<string, array{count: int, avgScore: float}>,
        //            categories: array<int, array<string, float|int|string>>,
        //            hardestQuestions: array<int, array<int|string, float|int|object|string>>,
        //            trends: array<int, array{date: \DateTime, sessions: int, avgScore: float}>
        //        }

        return [
            'sessions' => [
                'total'          => $totalSessions,
                'completed'      => $completedSessions,
                'inProgress'     => $inProgressSessions,
                'completionRate' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1) : 0,
            ],
            'scores' => [
                'average'     => $avgScore,
                'highest'     => $highestScore,
                'topSessions' => $topSessions,
            ],
            'answers' => [
                'total'           => $totalAnswers,
                'correct'         => $correctAnswers,
                'successRate'     => $globalSuccessRate,
                'avgResponseTime' => $avgResponseTime,
            ],
            'gameModes'        => $gameModeStats,
            'categories'       => $categoryStats,
            'hardestQuestions' => $hardestQuestions,
            'trends'           => $dailyStats,
        ];
    }

    /**
     * Retrieves statistics for a specific answer.
     *
     * @param QuizSessionAnswer $answer The answer to analyze
     *
     * @return array{
     *     answer: QuizSessionAnswer,
     *     questionStats: array{totalAnswers: int,correctAnswers: int,successRate: float,averageTime: float},
     *     comparison: array{fasterAnswers: int,totalAnswers: int,percentile: float},
     *     difficulty: string,
     *     timeAnalysis: array{userTime: int,averageTime: int,difference: int,percentageDifference: float,speed: string}
     * }
     */
    public function getAnswerStatistics(QuizSessionAnswer $answer): array
    {
        $question = $answer->getQuestion();

        // Stats globales pour cette question
        $questionStats = $this->getQuestionGlobalStatistics($question);

        // Comparaison avec les autres réponses à la même question
        $comparison = $this->compareAnswerToOthers($answer);

        return [
            'answer'        => $answer,
            'questionStats' => $questionStats,
            'comparison'    => $comparison,
            'difficulty'    => $this->calculateQuestionDifficulty($question),
            'timeAnalysis'  => $this->analyzeResponseTime($answer),
        ];
    }

    /**
     * Retrieves question statistics (most failed, easiest, most answered, and by category).
     *
     * @return array{
     *     hardest: array<int, array{
     *         0: object,
     *         categoryName: string,
     *         totalAnswers: int,
     *         wrongAnswers: int,
     *         avgResponseTime: float,
     *         failureRate: float
     *     }>,
     *     easiest: array<int, array{
     *         0: object,
     *         totalAnswers: int,
     *         correctAnswers: int,
     *         successRate: float
     *     }>,
     *     mostAnswered: array<int, array{
     *         0: object,
     *         totalAnswers: int,
     *         successRate: float
     *     }>,
     *     byCategory: array<int, array{
     *         categoryName: string,
     *         totalQuestions: int,
     *         totalAnswers: int,
     *         successRate: float
     *     }>
     * }
     */
    public function getQuestionStatistics(): array
    {
        $hardestQuestions      = $this->getHardestQuestions();
        $easiestQuestions      = $this->getEasiestQuestions();
        $mostAnsweredQuestions = $this->getMostAnsweredQuestions();
        $questionsByCategory   = $this->getQuestionStatsByCategory();

        return [
            'hardest'      => $hardestQuestions,
            'easiest'      => $easiestQuestions,
            'mostAnswered' => $mostAnsweredQuestions,
            'byCategory'   => $questionsByCategory,
        ];
    }

    // === MÉTHODES PRIVÉES ===
    /**
     * Calculates the duration of a session.
     *
     * @param QuizSession $session The quiz session
     */
    private function calculateSessionDuration(QuizSession $session): ?int
    {
        if (!$session->getStartedAt() || !$session->getFinishedAt()) {
            return null;
        }

        return $session->getFinishedAt()->getTimestamp() - $session->getStartedAt()->getTimestamp();
    }

    /**
     * Retrieves the rank of a session.
     *
     * @param QuizSession $session The quiz session
     */
    private function getSessionRank(QuizSession $session): int
    {
        return $this->sessionRepository->getSessionRank($session);
    }

    /**
     * Retrieves statistics by game mode.
     *
     * @return array<string, array{count: int, avgScore: float}>
     */
    private function getGameModeStatistics(): array
    {
        return $this->sessionRepository->getGameModeStatistics();
    }

    /**
     * Retrieves statistics by category.
     *
     * @return array<int, array{
     *      name: string,
     *      questionsCount: int,
     *      totalAnswers: int,
     *      successRate: float
     *  }>
     */
    private function getCategoryStatistics(): array
    {
        return $this->categoryRepository->getQuizStatistics();
    }

    /**
     * Retrieves the hardest questions.
     *
     * @param int $limit Maximum number of questions to return
     *
     * @return array<int, array{
     *      0: object, // Question entity
     *      categoryName: string,
     *      totalAnswers: int,
     *      wrongAnswers: int,
     *      avgResponseTime: float,
     *      failureRate: float
     *  }>
     */
    private function getHardestQuestions(int $limit = 20): array
    {
        return $this->questionRepository->getHardestQuestions($limit);
    }

    /**
     * Retrieves the easiest questions.
     *
     * @param int $limit Maximum number of questions to return
     *
     * @return array<int, array{
     *      0: object, // Question entity
     *      totalAnswers: int,
     *      correctAnswers: int,
     *      successRate: float
     *  }>
     */
    private function getEasiestQuestions(int $limit = 20): array
    {
        return $this->questionRepository->getEasiestQuestions($limit);
    }

    /**
     * Retrieves the most answered questions.
     *
     * @param int $limit Maximum number of questions to return
     *
     * @return array<int, array{
     *      0: object, // Question entity
     *      totalAnswers: int,
     *      successRate: float
     *  }>
     */
    private function getMostAnsweredQuestions(int $limit = 20): array
    {
        return $this->questionRepository->getMostAnsweredQuestions($limit);
    }

    /**
     * Retrieves question statistics by category.
     *
     * @return array<int, array{
     *      categoryName: string,
     *      totalQuestions: int,
     *      totalAnswers: int,
     *      successRate: float
     *  }>
     */
    private function getQuestionStatsByCategory(): array
    {
        return $this->questionRepository->getStatsByCategory();
    }

    /**
     * Retrieves daily statistics.
     *
     * @param int $days Number of days to analyze
     *
     * @return array<int, array{date: \DateTime, sessions: int, avgScore: float}>
     */
    private function getDailyStatistics(int $days): array
    {
        return $this->sessionRepository->getDailyStatistics($days);
    }

    /**
     * Retrieves global statistics for a question.
     *
     * @return array{
     *     totalAnswers: int<0, max>,
     *     correctAnswers: int<0, max>,
     *     successRate: float,
     *     averageTime: float
     * }
     */
    private function getQuestionGlobalStatistics(Question $question): array
    {
        $totalAnswers   = $this->answerRepository->count(['question' => $question]);
        $correctAnswers = $this->answerRepository->count(['question' => $question, 'isCorrect' => true]);
        $avgTime        = $this->answerRepository->getAverageTimeForQuestion($question);

        return [
            'totalAnswers'   => $totalAnswers,
            'correctAnswers' => $correctAnswers,
            'successRate'    => $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 1) : 0,
            'averageTime'    => $avgTime,
        ];
    }

    /**
     * Compares an answer to others for the same question.
     *
     * @return array{
     *     fasterAnswers?: int,
     *     totalAnswers?: int,
     *     percentile?: float
     * }|array{}
     */
    private function compareAnswerToOthers(QuizSessionAnswer $answer): array
    {
        $question = $answer->getQuestion();
        $userTime = $answer->getTime();

        if (!$question || !$userTime) {
            return [];
        }

        $fasterCount = $this->answerRepository->count([
            'question' => $question,
            'time'     => ['$lt' => $userTime],
        ]);

        $totalCount = $this->answerRepository->count(['question' => $question]);

        $percentile = $totalCount > 0 ? round((($totalCount - $fasterCount) / $totalCount) * 100, 1) : 0;

        return [
            'fasterAnswers' => $fasterCount,
            'totalAnswers'  => $totalCount,
            'percentile'    => $percentile,
        ];
    }

    /**
     * Calculates the difficulty of a question.
     *
     * @param Question $question The question
     */
    private function calculateQuestionDifficulty(Question $question): string
    {
        $stats       = $this->getQuestionGlobalStatistics($question);
        $successRate = $stats['successRate'];

        return match (true) {
            $successRate >= 80 => 'Facile',
            $successRate >= 60 => 'Moyen',
            $successRate >= 40 => 'Difficile',
            default            => 'Très difficile',
        };
    }

    /**
     * Analyzes response time.
     *
     * @param QuizSessionAnswer $answer The answer to analyze
     *
     * @return array{
     *     userTime: int,
     *     averageTime: int|float,
     *     difference: int|float,
     *     percentageDifference: float,
     *     speed: string
     * }
     */
    private function analyzeResponseTime(QuizSessionAnswer $answer): array
    {
        $time     = $answer->getTime();
        $question = $answer->getQuestion();

        if (!$time || !$question) {
            return [
                'userTime'             => 0,
                'averageTime'          => 0,
                'difference'           => 0,
                'percentageDifference' => 0.0,
                'speed'                => 'Inconnu',
            ];
        }

        $avgTime        = $this->answerRepository->getAverageTimeForQuestion($question);
        $difference     = $time - $avgTime;
        $percentageDiff = $avgTime > 0 ? round(($difference / $avgTime) * 100, 1) : 0;

        return [
            'userTime'             => $time,
            'averageTime'          => $avgTime,
            'difference'           => $difference,
            'percentageDifference' => $percentageDiff,
            'speed'                => $this->classifySpeed($percentageDiff),
        ];
    }

    /**
     * Classifies response speed.
     *
     * @param float $percentageDiff Percentage difference
     */
    private function classifySpeed(float $percentageDiff): string
    {
        return match (true) {
            $percentageDiff <= -50 => 'Très rapide',
            $percentageDiff <= -20 => 'Rapide',
            $percentageDiff <= 20  => 'Normal',
            $percentageDiff <= 50  => 'Lent',
            default                => 'Très lent',
        };
    }

    /**
     * Retrieves user performance statistics.
     *
     * @return array<int, array{
     *      email: string,
     *      totalSessions: int,
     *      avgScore: float,
     *      bestScore: int
     *  }>
     */
    public function getUserPerformanceStatistics(): array
    {
        return $this->sessionRepository->getUserPerformanceStats();
    }

    /**
     * Detects anomalies in sessions.
     *
     * @return array<int, array{type: string, count: int, severity: string, message: string}>
     */
    public function detectAnomalies(): array
    {
        $anomalies = [];

        // Sessions avec score 0
        $zeroScoreSessions = $this->sessionRepository->count(['score' => 0, 'status' => 'FINISHED']);
        if ($zeroScoreSessions > 0) {
            $anomalies[] = [
                'type'     => 'zero_scores',
                'count'    => $zeroScoreSessions,
                'severity' => 'warning',
                'message'  => 'Sessions terminées avec un score de 0',
            ];
        }

        // Sessions très courtes (moins de 30 secondes)
        $shortSessions = $this->sessionRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.finishedAt IS NOT NULL')
            ->andWhere('TIMESTAMPDIFF(SECOND, q.startedAt, q.finishedAt) < 30')
            ->getQuery()
            ->getSingleScalarResult();

        if ($shortSessions > 0) {
            $anomalies[] = [
                'type'     => 'short_sessions',
                'count'    => $shortSessions,
                'severity' => 'warning',
                'message'  => 'Sessions terminées en moins de 30 secondes',
            ];
        }

        return $anomalies;
    }

    /**
     * Retrieves weekly trends.
     *
     * @return array<int, array{
     *      week: int,
     *      year: int,
     *      sessions: int,
     *      avgScore: float
     *  }>
     */
    public function getWeeklyTrends(): array
    {
        $startDate = new \DateTime('-4 weeks');

        return $this->sessionRepository->createQueryBuilder('q')
            ->select('WEEK(q.startedAt) as week, YEAR(q.startedAt) as year,
                      COUNT(q.id) as sessions, AVG(q.score) as avgScore')
            ->where('q.startedAt >= :startDate')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('startDate', $startDate)
            ->groupBy('YEAR(q.startedAt), WEEK(q.startedAt)')
            ->orderBy('year', 'ASC')
            ->addOrderBy('week', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculates user retention metrics.
     *
     * @return array{
     *     totalUsers: int,
     *     returningUsers: int,
     *     retentionRate: float
     * }
     */
    public function getUserRetentionMetrics(): array
    {
        // Utilisateurs qui ont joué plusieurs fois
        $returningUsers = $this->sessionRepository->createQueryBuilder('q')
            ->select('COUNT(DISTINCT q.user)')
            ->leftJoin('q.user', 'u')
            ->where('u.id IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('u.id')
            ->having('COUNT(q.id) > 1')
            ->getQuery()
            ->getResult();

        $totalUsers = $this->sessionRepository->createQueryBuilder('q')
            ->select('COUNT(DISTINCT q.user)')
            ->leftJoin('q.user', 'u')
            ->where('u.id IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $retentionRate = $totalUsers > 0 ? round((count($returningUsers) / $totalUsers) * 100, 1) : 0;

        return [
            'totalUsers'     => $totalUsers,
            'returningUsers' => count($returningUsers),
            'retentionRate'  => $retentionRate,
        ];
    }
}
