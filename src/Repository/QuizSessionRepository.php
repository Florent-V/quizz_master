<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<QuizSession>
 */
class QuizSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizSession::class);
    }

    /**
     * Counts all quiz sessions.
     */
    public function getTotalCount(): int
    {
        return $this->count([]);
    }

    /**
     * Counts all active (IN_PROGRESS) quiz sessions.
     */
    public function getActiveSessionsCount(): int
    {
        return $this->count(['status' => 'IN_PROGRESS']);
    }

    /**
     * Calculates the average score of finished quiz sessions.
     */
    public function getAverageScore(): float
    {
        $result = $this->createQueryBuilder('q')
            ->select('AVG(q.score)')
            ->where('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $average = (float) ($result ?? 0);

        return round($average, 1);
    }

    /**
     * Retrieves the highest score among finished quiz sessions.
     */
    public function getHighestScore(): int
    {
        $result = $this->createQueryBuilder('q')
            ->select('MAX(q.score)')
            ->where('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Retrieves the top quiz sessions by score and completion time.
     *
     * @param int $limit Maximum number of sessions to return
     *
     * @return array<int, object> // Array of QuizSession entities
     */
    public function getTopSessions(int $limit = 10): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->orderBy('q.score', 'DESC')
            ->addOrderBy('q.finishedAt', 'ASC') // En cas d'égalité, le plus rapide gagne
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculates the rank of a quiz session based on score and completion time.
     *
     * @param QuizSession $session The quiz session to rank
     */
    public function getSessionRank(QuizSession $session): int
    {
        if (!$session->getFinishedAt()) {
            return 0;
        }

        return $this->createQueryBuilder('q')
            ->select('COUNT(q.id) + 1')
            ->where('q.score > :score')
            ->orWhere('q.score = :score AND q.finishedAt < :finishedAt')
            ->andWhere('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('score', $session->getScore())
            ->setParameter('finishedAt', $session->getFinishedAt())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retrieves statistics (count and average score) grouped by game mode.
     *
     * @return array<string, array{count: int, avgScore: float}>
     */
    public function getGameModeStatistics(): array
    {
        $result = $this->createQueryBuilder('q')
            ->select('q.gameMode, COUNT(q.id) as count, AVG(q.score) as avgScore')
            ->where('q.deletedAt IS NULL')
            ->groupBy('q.gameMode')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            // Si gameMode est un Enum, on récupère sa valeur (ou son name)
            $gameMode = $row['gameMode'] instanceof GameMode
                ? $row['gameMode']->value // ou ->name selon ton Enum
                : (string) $row['gameMode'];

            $avgScore = $row['avgScore'] ?? 0;

            $stats[$gameMode] = [
                'count'    => (int) $row['count'],
                'avgScore' => round((float) $avgScore, 1),
            ];
        }


        return $stats;
    }

    /**
     * Exports answer data to a structured array for reporting.
     *
     * @return array<int, array{
     *     ID: Uuid,
     *     Pseudo: string|null,
     *     Email: string,
     *     'Mode de Jeu': string,
     *     Statut: string,
     *     Score: int|null,
     *     Catégorie: string,
     *     'Sous-catégorie': string,
     *     'Commencé le': string,
     *     'Terminé le': string,
     *     'Créé le': string
     * }>
     */
    public function exportToArray(): array
    {
        $sessions = $this->findBy(
            ['deletedAt' => null],
            ['startedAt' => 'DESC']
        );

        $exportData = [];
        foreach ($sessions as $session) {
            $exportData[] = [
                'ID'             => $session->getId(),
                'Pseudo'         => $session->getPseudo(),
                'Email'          => $session->getUser()?->getEmail() ?? 'Anonyme',
                'Mode de Jeu'    => $session->getGameMode()->value,
                'Statut'         => $session->getStatus()->value,
                'Score'          => $session->getScore(),
                'Catégorie'      => $session->getCategory()?->getName()               ?? 'Non définie',
                'Sous-catégorie' => $session->getSubCategory()?->getName()            ?? 'Non définie',
                'Commencé le'    => $session->getStartedAt()?->format('d/m/Y H:i:s')  ?? '',
                'Terminé le'     => $session->getFinishedAt()?->format('d/m/Y H:i:s') ?? 'En cours',
                'Créé le'        => $session->getCreatedAt()->format('d/m/Y H:i:s'),
            ];
        }

        return $exportData;
    }

    /**
     * Calcule le score moyen pour un mode de jeu spécifique.
     */
    public function getAverageScoreByGameMode(GameMode $gameMode): float
    {
        $result = $this->createQueryBuilder('q')
            ->select('AVG(q.score)')
            ->where('q.gameMode = :gameMode')
            ->andWhere('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('gameMode', $gameMode)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float) ($result ?? 0), 1);
    }

    /**
     * Récupère le meilleur score pour un mode de jeu spécifique.
     */
    public function getBestScoreByGameMode(GameMode $gameMode): int
    {
        $result = $this->createQueryBuilder('q')
            ->select('MAX(q.score)')
            ->where('q.gameMode = :gameMode')
            ->andWhere('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('gameMode', $gameMode)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Counts quiz sessions started today.
     */
    public function getTodaysSessionsCount(): int
    {
        $today = new \DateTime('today');

        return $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.startedAt >= :today')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calculates the average score of quiz sessions started today.
     */
    public function getTodaysAverageScore(): float
    {
        $today = new \DateTime('today');

        $result = $this->createQueryBuilder('q')
            ->select('AVG(q.score)')
            ->where('q.startedAt >= :today')
            ->andWhere('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        return round($result ?? 0, 1);
    }

    /**
     * Retrieves daily statistics (sessions count and average score) for the last N days.
     *
     * @param int $days Number of days to look back
     *
     * @throws Exception
     *
     * @return array<int, array{date: \DateTime, sessions: int, avgScore: float}>
     */
    public function getDailyStatistics(int $days): array
    {
        $startDate = new \DateTime("-{$days} days");

        $result = $this->createQueryBuilder('q')
            ->select('DATE(q.startedAt) as date, COUNT(q.id) as sessions, AVG(q.score) as avgScore')
            ->where('q.startedAt >= :startDate')
            ->andWhere('q.deletedAt IS NULL')
            ->setParameter('startDate', $startDate)
            ->groupBy('date') // Utiliser l'alias au lieu de la fonction complète
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[] = [
                'date'     => new \DateTime($row['date']),
                'sessions' => $row['sessions'],
                'avgScore' => round((float) ($row['avgScore'] ?? 0), 1),
            ];
        }

        return $stats;
    }

    /**
     * Retrieves performance statistics (total sessions, average, min, and max score) grouped by game mode.
     *
     * @return array<int, array{
     *     gameMode: string,
     *     totalSessions: int,
     *     avgScore: float,
     *     minScore: int,
     *     maxScore: int
     * }>
     */
    public function getPerformanceByGameMode(): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.gameMode, COUNT(q.id) as totalSessions, AVG(q.score) as avgScore,
                  MIN(q.score) as minScore, MAX(q.score) as maxScore')
            ->where('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('q.gameMode')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves performance statistics (total sessions and average score) grouped by category.
     *
     * @return array<int, array{
     *     categoryName: string,
     *     totalSessions: int,
     *     avgScore: float
     * }>
     */
    public function getPerformanceByCategory(): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.category', 'c')
            ->select('c.name as categoryName, COUNT(q.id) as totalSessions, 
                  AVG(q.score) as avgScore')
            ->where('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->andWhere('c.id IS NOT NULL')
            ->groupBy('c.id')
            ->orderBy('avgScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts quiz sessions without any answers.
     */
    public function getSessionsWithoutAnswers(): int
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.quizSessionAnswers', 'a')
            ->select('COUNT(q.id)')
            ->where('a.id IS NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retrieves problematic quiz sessions (very low score or unusually long duration).
     *
     * @return array<int, object> // Array of QuizSession entities
     */
    public function getProblematicSessions(): array
    {
        // Sessions avec des scores anormalement bas ou des temps très longs
        return $this->createQueryBuilder('q')
            ->where('q.score < 10 OR (q.finishedAt IS NOT NULL AND q.startedAt IS NOT NULL 
                 AND TIMESTAMPDIFF(MINUTE, q.startedAt, q.finishedAt) > 60)')
            ->andWhere('q.deletedAt IS NULL')
            ->orderBy('q.startedAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves the top 5 peak hours for quiz session starts.
     *
     * @return array<int, array{hour: int, sessions: int}>
     */
    public function getPeakHours(): array
    {
        return $this->createQueryBuilder('q')
            ->select('HOUR(q.startedAt) as hour, COUNT(q.id) as sessions')
            ->where('q.deletedAt IS NULL')
            ->groupBy('HOUR(q.startedAt)')
            ->orderBy('sessions', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves performance statistics (total sessions, average, and best score) for users with at least 3 sessions.
     *
     * @return array<int, array{
     *     email: string,
     *     totalSessions: int,
     *     avgScore: float,
     *     bestScore: int
     * }>
     */
    public function getUserPerformanceStats(): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.user', 'u')
            ->select('u.email, COUNT(q.id) as totalSessions, AVG(q.score) as avgScore,
                  MAX(q.score) as bestScore')
            ->where('u.id IS NOT NULL')
            ->andWhere('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('u.id')
            ->having('COUNT(q.id) >= 3') // Au moins 3 sessions
            ->orderBy('avgScore', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves performance statistics for anonymous players (by pseudo).
     *
     * @return array<int, array{pseudo: string, totalSessions: int, avgScore: float, bestScore: int}>
     */
    public function getAnonymousPerformanceStats(): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.pseudo, COUNT(q.id) as totalSessions, AVG(q.score) as avgScore,
                  MAX(q.score) as bestScore')
            ->where('q.user IS NULL')
            ->andWhere('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('q.pseudo')
            ->having('COUNT(q.id) >= 3') // Au moins 3 sessions
            ->orderBy('avgScore', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves statistics for each game mode, including count and average score.
     * Ensures all game modes are represented, even if no data exists for them.
     *
     * @return array<string, array{count: int, avgScore: float}>
     */
    public function getGameModeStats(): array
    {
        $stats = $this->createQueryBuilder('s')
            ->select('s.gameMode, COUNT(s.id) as count, AVG(s.score) as avgScore')
            ->where('s.deletedAt IS NULL')
            ->groupBy('s.gameMode')
            ->getQuery()
            ->getResult();

        $gameModeStats = [];
        foreach ($stats as $stat) {
            $gameMode    = $stat['gameMode'];
            $gameModeKey = $gameMode instanceof GameMode
                ? $gameMode->value
                : (string) $gameMode;

            $gameModeStats[$gameModeKey] = [
                'count'    => (int) $stat['count'],
                'avgScore' => round((float) ($stat['avgScore'] ?? 0), 1),
            ];
        }

        foreach (GameMode::cases() as $mode) {
            if (!isset($gameModeStats[$mode->value])) {
                $gameModeStats[$mode->value] = ['count' => 0, 'avgScore' => 0];
            }
        }

        return $gameModeStats;
    }

    /**
     * Retrieves trend data for sessions over the last 30 days.
     *
     * Executes a query to calculate the number of sessions and average score per day,
     * then formats the results as an array of associative arrays.
     *
     * @return array<int, array{
     *     date: \DateTime,
     *     sessions: int,
     *     avgScore: float
     * }>
     */
    public function getTrendData(): array
    {
        $startDate = new \DateTime('-30 days');

        $trends = $this->createQueryBuilder('s')
            ->select('DATE(s.startedAt) as date, COUNT(s.id) as sessions, AVG(s.score) as avgScore')
            ->where('s.startedAt >= :startDate')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('startDate', $startDate)
            ->groupBy('DATE(s.startedAt)')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            function ($trend) {
                return [
                    'date'     => new \DateTime($trend['date']),
                    'sessions' => (int) $trend['sessions'],
                    'avgScore' => round($trend['avgScore'] ?? 0, 1),
                ];
            },
            $trends
        );
    }

    /**
     * Retrieves performance statistics for players by nickname (pseudo).
     *
     * @return array<int, array{nickname: string, totalSessions: int, avgScore: float, bestScore: int}>
     */
    public function getNicknamePerformanceStats(): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.pseudo as nickname, COUNT(q.id) as totalSessions, AVG(q.score) as avgScore,
                  MAX(q.score) as bestScore')
            ->where('q.pseudo IS NOT NULL')
            ->andWhere('q.finishedAt IS NOT NULL')
            ->andWhere('q.deletedAt IS NULL')
            ->groupBy('q.pseudo')
            ->having('COUNT(q.id) >= 3') // Au moins 3 sessions
            ->orderBy('avgScore', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves daily activity statistics for the last N days.
     *
     * @param int $days Number of days to look back
     *
     * @return array<int, array{date: \DateTime, sessionsCount: int, avgScore: float, successRate: float}>
     */
    public function getDailyActivityStats(int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");

        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT 
                DATE(s.started_at) as date,
                COUNT(s.id) as sessionsCount,
                AVG(s.score) as avgScore,
                (SUM(CASE WHEN sa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(sa.id), 0)) as successRate
            FROM quiz_session s
            LEFT JOIN quiz_session_answer sa ON s.id = sa.quiz_session_id AND sa.deleted_at IS NULL
            WHERE s.started_at >= :startDate
            AND s.deleted_at IS NULL
            GROUP BY DATE(s.started_at)
            ORDER BY date ASC
        ';

        $stmt   = $conn->prepare($sql);
        $result = $stmt->executeQuery(['startDate' => $startDate->format('Y-m-d H:i:s')]);

        $stats = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $stats[] = [
                'date'          => new \DateTime($row['date']),
                'sessionsCount' => (int) $row['sessionsCount'],
                'avgScore'      => round((float) ($row['avgScore'] ?? 0), 2),
                'successRate'   => round((float) ($row['successRate'] ?? 0), 2),
            ];
        }

        return $stats;
    }

    /**
     * Retrieves weekly activity statistics for the last N weeks.
     *
     * @param int $weeks Number of weeks to look back
     *
     * @return array<int, array{week: int, year: int, sessionsCount: int, avgScore: float}>
     */
    public function getWeeklyActivityStats(int $weeks = 12): array
    {
        $startDate = new \DateTime("-{$weeks} weeks");

        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT 
                WEEK(s.started_at) as week,
                YEAR(s.started_at) as year,
                COUNT(s.id) as sessionsCount,
                AVG(s.score) as avgScore
            FROM quiz_session s
            WHERE s.started_at >= :startDate
            AND s.deleted_at IS NULL
            GROUP BY YEAR(s.started_at), WEEK(s.started_at)
            ORDER BY year ASC, week ASC
        ';

        $stmt   = $conn->prepare($sql);
        $result = $stmt->executeQuery(['startDate' => $startDate->format('Y-m-d H:i:s')]);

        $stats = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $stats[] = [
                'week'          => (int) $row['week'],
                'year'          => (int) $row['year'],
                'sessionsCount' => (int) $row['sessionsCount'],
                'avgScore'      => round((float) ($row['avgScore'] ?? 0), 2),
            ];
        }

        return $stats;
    }

    /**
     * Retrieves game mode evolution data over the last N days.
     *
     * @param int $days Number of days to look back
     *
     * @return array<int, array{date: \DateTime, gameMode: string, avgScore: float, sessionsCount: int}>
     */
    public function getGameModeEvolution(int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");

        $qb = $this->createQueryBuilder('s')
            ->select('DATE(s.startedAt) as date, s.gameMode, AVG(s.score) as avgScore, COUNT(s.id) as sessionsCount')
            ->where('s.startedAt >= :startDate')
            ->andWhere('s.finishedAt IS NOT NULL')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('startDate', $startDate)
            ->groupBy('date', 's.gameMode')
            ->orderBy('date', 'ASC');

        $results = $qb->getQuery()->getResult();

        return array_map(function ($result) {
            $gameMode    = $result['gameMode'];
            $gameModeStr = $gameMode instanceof GameMode ? $gameMode->value : (string) $gameMode;

            return [
                'date'          => new \DateTime($result['date']),
                'gameMode'      => $gameModeStr,
                'avgScore'      => (float) $result['avgScore'],
                'sessionsCount' => (int) $result['sessionsCount'],
            ];
        }, $results);
    }
}
