<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\Role;
use App\Quiz\Service\QuizStatisticsService;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionAnswerRepository;
use App\Repository\QuizSessionRepository;
use App\Service\QuestionReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin-stats-tools', name: 'admin_stats_tools_')]
#[IsGranted(Role::ADMIN->value)]
class AdminStatsController extends AbstractController
{
    public function __construct(
        private readonly QuizStatisticsService $statisticsService,
        private readonly QuestionRepository $questionRepository,
        private readonly QuizSessionRepository $sessionRepository,
        private readonly QuizSessionAnswerRepository $answerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly QuestionReportService $questionReportService,
    ) {
    }

    #[Route('/global', name: 'global')]
    public function globalStats(): Response
    {
        $stats = $this->statisticsService->getGlobalStatistics();

        return $this->render('admin/stats/global.html.twig', [
            'stats' => $stats,
        ]);
    }

    #[Route('/performance', name: 'performance_analysis')]
    public function performanceAnalysis(): Response
    {
        // Analyse des performances par mode de jeu, catégorie, etc.
        $performanceData = [
            'gameModePerformance'  => $this->sessionRepository->getPerformanceByGameMode(),
            'categoryPerformance'  => $this->sessionRepository->getPerformanceByCategory(),
            'timeAnalysis'         => $this->answerRepository->getResponseTimeAnalysis(),
            'userPerformance'      => $this->sessionRepository->getUserPerformanceStats(),
            'anonymousPerformance' => $this->sessionRepository->getAnonymousPerformanceStats(),
            'difficultyAnalysis'   => $this->answerRepository->getPerformanceByDifficulty(),
        ];

        return $this->render('admin/stats/performance.html.twig', [
            'page_title' => 'Statistiques de performance',
            'data'       => $performanceData,
        ]);
    }

    #[Route('/questions', name: 'question_analytics')]
    public function questionAnalytics(): Response
    {
        $questionStats = $this->statisticsService->getQuestionStatistics();

        return $this->render('admin/stats/questions.html.twig', [
            'stats' => $questionStats,
        ]);
    }

    #[Route('/questions/complete-report', name: 'question_complete_report')]
    public function questionCompleteReport(): Response
    {
        $report = $this->questionReportService->generateCompleteReport();

        return $this->render('admin/stats/question_report.html.twig', [
            'report' => $report,
        ]);
    }

    #[Route('/difficulties', name: 'difficult_questions')]
    public function difficultQuestions(): Response
    {
        $hardestQuestions = $this->questionRepository->getHardestQuestions(50);
        $categoryFailures = $this->questionRepository->getCategoryFailureRates();

        return $this->render('admin/stats/difficult_questions.html.twig', [
            'hardestQuestions' => $hardestQuestions,
            'categoryFailures' => $categoryFailures,
        ]);
    }

    #[Route('/performance-issues', name: 'performance_issues')]
    public function performanceIssues(): Response
    {
        // Détection des problèmes de performance
        $issues = [
            'slowQuestions'        => $this->questionRepository->getQuestionsWithSlowResponses(),
            'unbalancedCategories' => $this->questionRepository->getUnbalancedCategories(),
            'problematicSessions'  => $this->sessionRepository->getProblematicSessions(),
            'timeoutAnalysis'      => $this->answerRepository->getTimeoutAnalysis(),
        ];

        return $this->render('admin/stats/performance_issues.html.twig', [
            'issues' => $issues,
        ]);
    }

    #[Route('/export', name: 'export')]
    public function exportTools(): Response
    {
        $exportStats = [
            'totalSessions'  => $this->sessionRepository->count([]),
            'totalAnswers'   => $this->answerRepository->count([]),
            'totalQuestions' => $this->questionRepository->count([]),
            'lastExports'    => $this->getLastExportInfo(),
        ];

        return $this->render('admin/system/export.html.twig', [
            'stats' => $exportStats,
        ]);
    }

    #[Route('/system-health', name: 'system_health')]
    public function systemHealth(): Response
    {
        $healthData = [
            'database'        => $this->checkDatabaseHealth(),
            'performance'     => $this->checkPerformanceMetrics(),
            'dataIntegrity'   => $this->checkDataIntegrity(),
            'systemResources' => $this->getSystemResourcesInfo(),
        ];

        return $this->render('admin/system/health.html.twig', [
            'health' => $healthData,
        ]);
    }

    #[Route('/realtime', name: 'realtime')]
    public function realtimeStats(): JsonResponse
    {
        // API pour les stats en temps réel (pour rafraîchissement AJAX)
        $realtimeData = [
            'activeSessions' => $this->sessionRepository->count(['status' => 'IN_PROGRESS']),
            'todaysSessions' => $this->sessionRepository->getTodaysSessionsCount(),
            'todaysScore'    => $this->sessionRepository->getTodaysAverageScore(),
            'currentLoad'    => $this->getCurrentSystemLoad(),
        ];

        return new JsonResponse($realtimeData);
    }

    // === MÉTHODES PRIVÉES ===

    /**
     * @return array{
     *     sessions: array{date: \DateTime, count: int},
     *     answers: array{date: \DateTime, count: int},
     *     questions: array{date: \DateTime, count: int}
     * }
     */
    private function getLastExportInfo(): array
    {
        // Simule les infos des derniers exports
        // En réalité, vous pourriez avoir une entité ExportLog
        return [
            'sessions'  => ['date' => new \DateTime('-2 days'), 'count' => 1250],
            'answers'   => ['date' => new \DateTime('-1 week'), 'count' => 15600],
            'questions' => ['date' => new \DateTime('-1 month'), 'count' => 850],
        ];
    }

    /**
     * @return array{
     *     connection: string,
     *     queryTime?: float,
     *     error?: string,
     *     status: string
     * }
     */
    private function checkDatabaseHealth(): array
    {
        try {
            // Test de connexion simple
            $this->entityManager->getConnection()->connect();
            $connectionStatus = 'OK';

            // Test de performance basique
            $start = microtime(true);
            $this->sessionRepository->findOneBy([], ['id' => 'ASC']);
            $queryTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'connection' => $connectionStatus,
                'queryTime'  => $queryTime,
                'status'     => $queryTime < 100 ? 'healthy' : ($queryTime < 500 ? 'warning' : 'critical'),
            ];
        } catch (\Exception $e) {
            return [
                'connection' => 'ERROR',
                'error'      => $e->getMessage(),
                'status'     => 'critical',
            ];
        }
    }

    /**
     * @return array{
     *     avgResponseTime: float,
     *     slowQueries: int,
     *     status: string
     * }
     */
    private function checkPerformanceMetrics(): array
    {
        $avgResponseTime = $this->answerRepository->getAverageResponseTime();
        $slowQueries     = $this->answerRepository->getSlowQueriesCount();

        return [
            'avgResponseTime' => $avgResponseTime,
            'slowQueries'     => $slowQueries,
            'status'          => $avgResponseTime < 2000 ? 'healthy' : 'warning',
        ];
    }

    /**
     * @return array{
     *     issues: list<string>,
     *     status: string,
     *     issuesCount: int
     * }
     */
    private function checkDataIntegrity(): array
    {
        $issues = [];

        // Vérifier les sessions sans réponses
        $sessionsWithoutAnswers = $this->sessionRepository->getSessionsWithoutAnswers();
        if ($sessionsWithoutAnswers > 0) {
            $issues[] = "Sessions sans réponses : $sessionsWithoutAnswers";
        }

        // Vérifier les réponses orphelines
        $orphanAnswers = $this->answerRepository->getOrphanAnswers();
        if ($orphanAnswers > 0) {
            $issues[] = "Réponses orphelines : $orphanAnswers";
        }

        // Vérifier les questions sans catégorie
        $questionsWithoutCategory = $this->questionRepository->getQuestionsWithoutCategory();
        if ($questionsWithoutCategory > 0) {
            $issues[] = "Questions sans catégorie : $questionsWithoutCategory";
        }

        return [
            'issues'      => $issues,
            'status'      => empty($issues) ? 'healthy' : 'warning',
            'issuesCount' => count($issues),
        ];
    }

    /**
     * @return array{
     *     memoryUsage: string,
     *     memoryLimit: string,
     *     memoryPercent: float,
     *     phpVersion: string,
     *     status: string
     * }
     */
    private function getSystemResourcesInfo(): array
    {
        $memoryUsage      = memory_get_usage(true);
        $memoryLimit      = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        $memoryPercent    = $memoryLimitBytes > 0 ? round(($memoryUsage / $memoryLimitBytes) * 100, 1) : 0;

        return [
            'memoryUsage'   => $this->formatBytes($memoryUsage),
            'memoryLimit'   => $memoryLimit,
            'memoryPercent' => $memoryPercent,
            'phpVersion'    => PHP_VERSION,
            'status'        => $memoryPercent < 80 ? 'healthy' : 'warning',
        ];
    }

    /**
     * @return array{
     *     activeSessions: int,
     *     questionsPerHour: int,
     *     peakHours: array<int, array<string, int>>
     * }
     */
    private function getCurrentSystemLoad(): array
    {
        return [
            'activeSessions'   => $this->sessionRepository->count(['status' => 'IN_PROGRESS']),
            'questionsPerHour' => $this->answerRepository->getQuestionsPerHour(),
            'peakHours'        => $this->sessionRepository->getPeakHours(),
        ];
    }

    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last  = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        return match ($last) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };
    }

    private function formatBytes(int $size, int $precision = 2): string
    {
        $base     = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}
