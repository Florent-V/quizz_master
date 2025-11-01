<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Quiz\Service\QuizStatisticsService;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionRepository;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Service for exporting statistics in various formats.
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
readonly class StatisticsExportService
{
    public function __construct(
        private QuizStatisticsService $statisticsService,
        private QuizSessionRepository $sessionRepository,
        private QuestionRepository $questionRepository,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * Generates global statistics data for JSON export.
     *
     * @return array<string, mixed>
     */
    public function generateGlobalStatsData(): array
    {
        $globalStats = $this->statisticsService->getGlobalStatistics();

        // @phpstan-ignore-next-line
        $globalStats['scores']['topSessions'] = $this->serializer->normalize(
            $globalStats['scores']['topSessions'],
            null,
            ['groups' => ['session:export']]
        );

        // @phpstan-ignore-next-line
        $globalStats['hardestQuestions'] = $this->serializer->normalize(
            $globalStats['hardestQuestions'],
            null,
            ['groups' => ['question:export']]
        );

        return [
            'exportDate' => (new \DateTime())->format('Y-m-d H:i:s'),
            'statistics' => $globalStats,
        ];
    }

    /**
     * Writes performance statistics to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    public function writePerformanceStatsToCsv($handle): void
    {
        // BOM UTF-8 pour Excel
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // En-têtes
        fputcsv($handle, [
            'Mode de Jeu',
            'Nombre de Sessions',
            'Score Moyen',
            'Score Maximum',
            'Taux de Réussite',
            'Temps Moyen (s)',
        ], ';');

        // Récupération des données de performance par mode de jeu
        $gameModeStats = $this->sessionRepository->getGameModeStats();

        foreach ($gameModeStats as $gameModeStr => $stat) {
            $sessionsCount = $stat['count'] ?? 0;

            // Conversion en objet GameMode si nécessaire
            try {
                $gameModeEnum = GameMode::from($gameModeStr);
            } catch (\ValueError $e) {
                continue; // Skip invalid game modes
            }

            // Récupération des statistiques détaillées
            $averageScore = $this->sessionRepository->getAverageScoreByGameMode($gameModeEnum);
            $bestScore    = $this->sessionRepository->getBestScoreByGameMode($gameModeEnum);

            // Calcul du taux de réussite moyen
            $successRate = $this->calculateSuccessRateForMode($gameModeStr);
            $averageTime = $this->calculateAverageTimeForMode($gameModeStr);

            fputcsv($handle, [
                $gameModeStr,
                $sessionsCount,
                number_format($averageScore, 2, ',', ' '),
                $bestScore,
                number_format($successRate, 2, ',', ' ') . '%',
                number_format($averageTime, 1, ',', ' '),
            ], ';');
        }

        // Section performance utilisateurs
        $this->writeUserPerformanceSection($handle);

        // Section performance par pseudonyme
        $this->writeNicknamePerformanceSection($handle);
    }

    /**
     * Writes user performance section to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    private function writeUserPerformanceSection($handle): void
    {
        fputcsv($handle, [], ';'); // Ligne vide
        fputcsv($handle, ['Performance par Utilisateur'], ';');
        fputcsv($handle, ['Email', 'Sessions', 'Score Moyen', 'Meilleur Score'], ';');

        $userPerformance = $this->sessionRepository->getUserPerformanceStats();
        foreach ($userPerformance as $userStat) {
            fputcsv($handle, [
                $userStat['email']         ?? 'Anonyme',
                $userStat['totalSessions'] ?? 0,
                number_format((float) ($userStat['avgScore'] ?? 0), 2, ',', ' '),
                $userStat['bestScore'] ?? 0,
            ], ';');
        }
    }

    /**
     * Writes nickname performance section to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    private function writeNicknamePerformanceSection($handle): void
    {
        fputcsv($handle, [], ';');
        fputcsv($handle, ['Performance par Pseudonyme'], ';');
        fputcsv($handle, ['Pseudonyme', 'Sessions', 'Score Moyen', 'Meilleur Score'], ';');

        $nicknamePerformance = $this->sessionRepository->getNicknamePerformanceStats();
        foreach ($nicknamePerformance as $nickStat) {
            fputcsv($handle, [
                $nickStat['nickname']      ?? 'Anonyme',
                $nickStat['totalSessions'] ?? 0,
                number_format((float) ($nickStat['avgScore'] ?? 0), 2, ',', ' '),
                $nickStat['bestScore'] ?? 0,
            ], ';');
        }
    }

    /**
     * Writes problems report to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    public function writeProblemsReportToCsv($handle): void
    {
        // BOM UTF-8
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Titre du rapport
        fputcsv($handle, ['RAPPORT DES PROBLEMES ET ANOMALIES'], ';');
        fputcsv($handle, ['Date: ' . date('d/m/Y H:i:s')], ';');
        fputcsv($handle, [], ';');

        // Section 1: Sessions problématiques
        $this->writeProblematicSessionsSection($handle);

        // Section 2: Questions avec réponses lentes
        $this->writeSlowQuestionsSection($handle);

        // Section 3: Questions avec peu de données
        $this->writeFewAnswersQuestionsSection($handle);

        // Section 4: Questions avec taux d'échec extrême
        $this->writeExtremeFailureQuestionsSection($handle);
    }

    /**
     * Writes problematic sessions section to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    private function writeProblematicSessionsSection($handle): void
    {
        fputcsv($handle, ['1. SESSIONS PROBLEMATIQUES'], ';');
        fputcsv($handle, ['Session ID', 'Mode', 'Score', 'Réponses', 'Durée', 'Problème'], ';');

        $problemSessions = $this->sessionRepository->getProblematicSessions();

        foreach ($problemSessions as $session) {
            fputcsv($handle, [
                $session->getId(),
                $session->getGameMode()->value ?? 'N/A',
                $session->getScore()           ?? 0,
                $session->getQuizSessionAnswers()->count(),
                $this->formatDuration($session->getStartedAt(), $session->getFinishedAt()),
                $this->identifyProblem($session),
            ], ';');
        }
    }

    /**
     * Writes slow questions section to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    private function writeSlowQuestionsSection($handle): void
    {
        fputcsv($handle, [], ';');
        fputcsv($handle, ['2. QUESTIONS AVEC TEMPS DE REPONSE LENT (>10s)'], ';');
        fputcsv($handle, ['Question ID', 'Question', 'Temps Moyen (s)', 'Difficulté'], ';');

        $slowQuestions = $this->questionRepository->getQuestionsWithSlowResponses(20);
        foreach ($slowQuestions as $questionData) {
            // Le résultat peut être ['entity' => Question, 'avgTime' => float] ou [0 => Question, 'avgTime' => float]
            $question = $questionData['entity']  ?? $questionData[0] ?? null;
            $avgTime  = $questionData['avgTime'] ?? 0;

            if (null === $question) {
                continue;
            }

            fputcsv($handle, [
                $question->getId(),
                substr($question->getContent(), 0, 100),
                number_format((float) $avgTime, 2, ',', ' '),
                $question->getDifficulty()->value ?? 'N/A',
            ], ';');
        }
    }

    /**
     * Writes few answers questions section to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    private function writeFewAnswersQuestionsSection($handle): void
    {
        fputcsv($handle, [], ';');
        fputcsv($handle, ['3. QUESTIONS AVEC PEU DE DONNEES (<5 réponses)'], ';');
        fputcsv($handle, ['Question ID', 'Question', 'Nombre Réponses', 'Catégorie'], ';');

        $fewAnswersQuestions = $this->questionRepository->getQuestionsWithFewAnswers(5, 20);
        foreach ($fewAnswersQuestions as $questionData) {
            $question    = $questionData['entity']      ?? $questionData[0] ?? null;
            $answerCount = $questionData['answerCount'] ?? 0;

            if (null === $question) {
                continue;
            }

            fputcsv($handle, [
                $question->getId(),
                substr($question->getContent(), 0, 100),
                $answerCount,
                $question->getCategory()?->getName() ?? 'N/A',
            ], ';');
        }
    }

    /**
     * Writes extreme failure questions section to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    private function writeExtremeFailureQuestionsSection($handle): void
    {
        fputcsv($handle, [], ';');
        fputcsv($handle, ['4. QUESTIONS AVEC TAUX ECHEC EXTREME'], ';');
        fputcsv($handle, ['Question ID', 'Question', 'Taux Réussite (%)', 'Réponses Totales'], ';');

        $extremeFailureQuestions = $this->questionRepository->getQuestionsWithExtremeFailureRate(20);
        foreach ($extremeFailureQuestions as $questionData) {
            $question     = $questionData['entity']       ?? $questionData[0] ?? null;
            $successRate  = $questionData['successRate']  ?? 0;
            $totalAnswers = $questionData['totalAnswers'] ?? 0;

            if (null === $question) {
                continue;
            }

            fputcsv($handle, [
                $question->getId(),
                substr($question->getContent(), 0, 100),
                number_format((float) $successRate, 2, ',', ' '),
                $totalAnswers,
            ], ';');
        }
    }

    /**
     * Writes trends statistics to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    public function writeTrendsStatsToCsv($handle): void
    {
        // BOM UTF-8
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // En-têtes
        fputcsv($handle, ['TENDANCES TEMPORELLES'], ';');
        fputcsv($handle, [], ';');

        // Tendances par jour
        $this->writeDailyTrendsSection($handle);

        // Tendances par semaine
        $this->writeWeeklyTrendsSection($handle);

        // Évolution des scores par mode de jeu
        $this->writeGameModeEvolutionSection($handle);
    }

    /**
     * Writes daily trends section to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    private function writeDailyTrendsSection($handle): void
    {
        fputcsv($handle, ['Activité par Jour'], ';');
        fputcsv($handle, ['Date', 'Sessions', 'Score Moyen', 'Taux Réussite (%)'], ';');

        $dailyStats = $this->sessionRepository->getDailyActivityStats(30);
        foreach ($dailyStats as $dayStat) {
            fputcsv($handle, [
                $dayStat['date']->format('d/m/Y'),
                $dayStat['sessionsCount'] ?? 0,
                number_format((float) ($dayStat['avgScore'] ?? 0), 2, ',', ' '),
                number_format((float) ($dayStat['successRate'] ?? 0), 2, ',', ' '),
            ], ';');
        }
    }

    /**
     * Writes weekly trends section to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    private function writeWeeklyTrendsSection($handle): void
    {
        fputcsv($handle, [], ';');
        fputcsv($handle, ['Activité par Semaine'], ';');
        fputcsv($handle, ['Semaine', 'Sessions', 'Score Moyen'], ';');

        $weeklyStats = $this->sessionRepository->getWeeklyActivityStats(12);
        foreach ($weeklyStats as $weekStat) {
            fputcsv($handle, [
                'Semaine ' . $weekStat['week'] . ' - ' . $weekStat['year'],
                $weekStat['sessionsCount'] ?? 0,
                number_format((float) ($weekStat['avgScore'] ?? 0), 2, ',', ' '),
            ], ';');
        }
    }

    /**
     * Writes game mode evolution section to CSV handle.
     *
     * @param resource $handle File handle for CSV writing
     */
    private function writeGameModeEvolutionSection($handle): void
    {
        fputcsv($handle, [], ';');
        fputcsv($handle, ['Evolution des Scores par Mode de Jeu'], ';');
        fputcsv($handle, ['Date', 'Mode', 'Score Moyen', 'Nombre Sessions'], ';');

        $modeEvolution = $this->sessionRepository->getGameModeEvolution(30);
        foreach ($modeEvolution as $evolution) {
            fputcsv($handle, [
                $evolution['date']->format('d/m/Y'),
                $evolution['gameMode'],
                number_format($evolution['avgScore'], 2, ',', ' '),
                $evolution['sessionsCount'],
            ], ';');
        }
    }

    /**
     * Calculates success rate for a specific game mode.
     */
    private function calculateSuccessRateForMode(string $gameMode): float
    {
        $sessions = $this->sessionRepository->findBy([
            'gameMode' => $gameMode,
        ]);

        if (empty($sessions)) {
            return 0.0;
        }

        $totalCorrect = 0;
        $totalAnswers = 0;

        foreach ($sessions as $session) {
            foreach ($session->getQuizSessionAnswers() as $answer) {
                ++$totalAnswers;
                if ($answer->isCorrect()) {
                    ++$totalCorrect;
                }
            }
        }

        return $totalAnswers > 0 ? ($totalCorrect / $totalAnswers) * 100 : 0.0;
    }

    /**
     * Calculates average time for a specific game mode.
     */
    private function calculateAverageTimeForMode(string $gameMode): float
    {
        $sessions = $this->sessionRepository->findBy([
            'gameMode' => $gameMode,
        ]);

        if (empty($sessions)) {
            return 0.0;
        }

        $totalTime = 0;
        $count     = 0;

        foreach ($sessions as $session) {
            foreach ($session->getQuizSessionAnswers() as $answer) {
                if (null !== $answer->getTime()) {
                    $totalTime += $answer->getTime();
                    ++$count;
                }
            }
        }

        return $count > 0 ? $totalTime / $count : 0.0;
    }

    /**
     * Formats duration between two dates.
     */
    private function formatDuration(?\DateTimeInterface $start, ?\DateTimeInterface $end): string
    {
        if (!$start || !$end) {
            return 'N/A';
        }

        $diff = $start->diff($end);

        if ($diff->h > 0) {
            return sprintf('%dh %dm', $diff->h, $diff->i);
        }

        if ($diff->i > 0) {
            return sprintf('%dm %ds', $diff->i, $diff->s);
        }

        return sprintf('%ds', $diff->s);
    }

    /**
     * Identifies the problem with a session.
     */
    private function identifyProblem(QuizSession $session): string
    {
        $problems = $this->collectSessionProblems($session);

        return !empty($problems) ? implode(', ', $problems) : 'Autre';
    }

    /**
     * Collects all problems for a session.
     *
     * @return array<string>
     */
    private function collectSessionProblems(QuizSession $session): array
    {
        $problems = [];

        $this->checkScoreProblems($session, $problems);
        $this->checkAnswersProblems($session, $problems);
        $this->checkFinishProblems($session, $problems);
        $this->checkDurationProblems($session, $problems);

        return $problems;
    }

    /**
     * Checks for score-related problems.
     *
     * @param array<string> $problems
     */
    private function checkScoreProblems(QuizSession $session, array &$problems): void
    {
        if (null === $session->getScore() || 0 === $session->getScore()) {
            $problems[] = 'Score nul';
        }
    }

    /**
     * Checks for answer-related problems.
     *
     * @param array<string> $problems
     */
    private function checkAnswersProblems(QuizSession $session, array &$problems): void
    {
        if (0 === $session->getQuizSessionAnswers()->count()) {
            $problems[] = 'Aucune réponse';
        }
    }

    /**
     * Checks if session is finished.
     *
     * @param array<string> $problems
     */
    private function checkFinishProblems(QuizSession $session, array &$problems): void
    {
        if (!$session->getFinishedAt()) {
            $problems[] = 'Non terminée';
        }
    }

    /**
     * Checks for duration-related problems.
     *
     * @param array<string> $problems
     */
    private function checkDurationProblems(QuizSession $session, array &$problems): void
    {
        $duration = $this->getDurationInSeconds($session->getStartedAt(), $session->getFinishedAt());

        if ($duration < 10) {
            $problems[] = 'Trop rapide';
        }

        if ($duration > 3600) {
            $problems[] = 'Trop longue';
        }
    }

    /**
     * Gets duration in seconds between two dates.
     */
    private function getDurationInSeconds(?\DateTimeInterface $start, ?\DateTimeInterface $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        return $end->getTimestamp() - $start->getTimestamp();
    }
}
