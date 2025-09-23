<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\QuizSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
            $gameMode = $row['gameMode'] instanceof \App\Enum\GameMode
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
     * Exports quiz session data to a structured array for reporting.
     *
     * @return array<int, array{
     *     ID: int,
     *     Pseudo: string,
     *     Email: string,
     *     'Mode de Jeu': string,
     *     Statut: string,
     *     Score: int,
     *     Catégorie: string,
     *     'Sous-catégorie': string,
     *     'Commencé le': string,
     *     'Terminé le': string,
     *     'Créé le': string
     * }>
     */
    public function exportToArray(): array
    {
        $sessions = $this->createQueryBuilder('q')
            ->leftJoin('q.user', 'u')
            ->leftJoin('q.category', 'c')
            ->leftJoin('q.subCategory', 'sc')
            ->select('q.id, q.pseudo, u.email as userEmail, q.gameMode, q.status, 
                  q.score, q.startedAt, q.finishedAt, c.name as categoryName, 
                  sc.name as subCategoryName, q.createdAt')
            ->where('q.deletedAt IS NULL')
            ->orderBy('q.startedAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $exportData = [];
        foreach ($sessions as $session) {
            $exportData[] = [
                'ID'             => $session['id'],
                'Pseudo'         => $session['pseudo'],
                'Email'          => $session['userEmail'] ?? 'Anonyme',
                'Mode de Jeu'    => $session['gameMode'],
                'Statut'         => $session['status'],
                'Score'          => $session['score'],
                'Catégorie'      => $session['categoryName']    ?? 'Non définie',
                'Sous-catégorie' => $session['subCategoryName'] ?? 'Non définie',
                'Commencé le'    => $session['startedAt']->format('d/m/Y H:i:s'),
                'Terminé le'     => $session['finishedAt'] ? $session['finishedAt']->format('d/m/Y H:i:s') : 'En cours',
                'Créé le'        => $session['createdAt']->format('d/m/Y H:i:s'),
            ];
        }

        return $exportData;
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
            ->groupBy('DATE(q.startedAt)')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[] = [
                'date'     => new \DateTime($row['date']),
                'sessions' => $row['sessions'],
                'avgScore' => round($row['avgScore'] ?? 0, 1),
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
}
