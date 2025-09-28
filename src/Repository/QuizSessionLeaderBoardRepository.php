<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Enum\QuizSessionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizSession>
 */
class QuizSessionLeaderBoardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizSession::class);
    }

    /**
     *  Fetches the top 3 quiz sessions for a given game mode.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findTopThreeByGameMode(GameMode $gameMode): array
    {
        return $this->createBaseHallOfFameQueryBuilder()
            ->andWhere('qs.gameMode = :gameMode')
            ->setParameter('gameMode', $gameMode)
            ->orderBy('qs.score', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
    }

    /**
     * Creates a QueryBuilder for the Hall of Fame, excluding specified session IDs.
     *
     * @param array<int> $excludedIds List of session IDs to exclude
     */
    public function createHallOfFameQueryBuilder(GameMode $gameMode, array $excludedIds): QueryBuilder
    {
        $qb = $this->createBaseHallOfFameQueryBuilder()
            ->andWhere('qs.gameMode = :gameMode')
            ->setParameter('gameMode', $gameMode)
            ->orderBy('qs.score', 'DESC');

        if (!empty($excludedIds)) {
            $qb->andWhere($qb->expr()->notIn('qs.id', ':excludedIds'))
                ->setParameter('excludedIds', $excludedIds);
        }

        return $qb;
    }

    /**
     * Creates a base QueryBuilder for the Hall of Fame.
     */
    private function createBaseHallOfFameQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('qs')
            ->select(
                'qs.id',
                'qs.score',
                'qs.pseudo',
                'COALESCE(u.userName, qs.pseudo) as player_name',
                'COUNT(qsa.id) as total_questions',
                'SUM(CASE WHEN qsa.isCorrect = true THEN 1 ELSE 0 END) as correct_answers',
                'TIMESTAMPDIFF(SECOND, qs.startedAt, qs.finishedAt) as duration'
            )
            ->leftJoin('qs.user', 'u')
            ->join('qs.quizSessionAnswers', 'qsa')
            ->andWhere('qs.status = :status')
            ->setParameter('status', QuizSessionStatus::Finished)
            ->groupBy('qs.id, u.id');
    }

    /**
     * Fetches leaderboard data with pagination and filters.
     *
     * @return array<string, mixed>
     */
    public function getLeaderboardData(?GameMode $gameMode = null, int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('qs')
            ->select([
                'qs.id',
                'qs.score',
                'qs.pseudo',
                'qs.gameMode',
                'qs.finishedAt',
                'qs.startedAt',
                'u.id as userId',
                'u.userName',
                'c.name as categoryName',
                'sc.name as subCategoryName',
                'COUNT(qsa.id) as totalQuestions',
                'SUM(CASE WHEN qsa.isCorrect = 1 THEN 1 ELSE 0 END) as correctAnswers',
                'AVG(qsa.time) as averageTime',
            ])
            ->leftJoin('qs.user', 'u')
            ->leftJoin('qs.category', 'c')
            ->leftJoin('qs.subCategory', 'sc')
            ->leftJoin('qs.quizSessionAnswers', 'qsa')
            ->where('qs.status = :finishedStatus')
            ->andWhere('qs.deletedAt IS NULL')
            ->groupBy('qs.id', 'u.id', 'c.id', 'sc.id')
            ->orderBy('qs.score', 'DESC')
            ->addOrderBy('qs.finishedAt', 'ASC')
            ->setParameter('finishedStatus', 'FINISHED')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($gameMode) {
            $qb->andWhere('qs.gameMode = :gameMode')
                ->setParameter('gameMode', $gameMode->value);
        }

        $results = $qb->getQuery()->getResult();

        // Ajouter le rang à chaque résultat
        foreach ($results as $index => &$result) {
            $result['rank'] = $offset + $index + 1;

            // Calculer la durée de la partie
            if ($result['startedAt'] && $result['finishedAt']) {
                $duration                    = $result['finishedAt']->diff($result['startedAt']);
                $result['duration']          = $duration->i * 60 + $duration->s; // en secondes
                $result['durationFormatted'] = sprintf('%02d:%02d', $duration->i, $duration->s);
            }

            // Calculer le pourcentage de réussite
            $result['successRate'] = $result['totalQuestions'] > 0
                ? round(($result['correctAnswers'] / $result['totalQuestions']) * 100, 1)
                : 0;
        }

        return $results;
    }

    /**
     * Fetches podium data (top 3) for a game mode.
     *
     * @param GameMode $gameMode The game mode
     *
     * @return array<string, mixed>
     */
    public function getPodiumData(GameMode $gameMode): array
    {
        $qb = $this->createQueryBuilder('qs')
            ->select([
                'qs.id',
                'qs.score',
                'qs.pseudo',
                'qs.finishedAt',
                'qs.startedAt',
                'u.id as userId',
                'u.userName',
                'COUNT(qsa.id) as totalQuestions',
                'SUM(CASE WHEN qsa.isCorrect = 1 THEN 1 ELSE 0 END) as correctAnswers',
            ])
            ->leftJoin('qs.user', 'u')
            ->leftJoin('qs.quizSessionAnswers', 'qsa')
            ->where('qs.status = :finishedStatus')
            ->andWhere('qs.gameMode = :gameMode')
            ->andWhere('qs.deletedAt IS NULL')
            ->groupBy('qs.id', 'u.id')
            ->orderBy('qs.score', 'DESC')
            ->addOrderBy('qs.finishedAt', 'ASC')
            ->setParameter('finishedStatus', 'FINISHED')
            ->setParameter('gameMode', $gameMode->value)
            ->setMaxResults(3);

        $results = $qb->getQuery()->getResult();

        foreach ($results as &$result) {
            if ($result['startedAt'] && $result['finishedAt']) {
                $duration = $result['finishedAt']->diff($result['startedAt']);
                //                $result['duration'] = $duration->i * 60 + $result['s'];
                $result['duration']          = $duration->i * 60 + $duration->s;
                $result['durationFormatted'] = sprintf('%02d:%02d', $duration->i, $duration->s);
            }

            $result['successRate'] = $result['totalQuestions'] > 0
                ? round(($result['correctAnswers'] / $result['totalQuestions']) * 100, 1)
                : 0;
        }

        return $results;
    }

    /**
     * Fetches global application statistics.
     *
     * @return array<string, mixed>
     */
    public function getGlobalStats(): array
    {
        $qb = $this->createQueryBuilder('qs')
            ->select([
                'COUNT(qs.id) as totalGames',
                'COUNT(DISTINCT qs.user) as totalPlayers',
                'AVG(qs.score) as averageScore',
                'MAX(qs.score) as highestScore',
                'qs.gameMode',
                'SUM(CASE WHEN qs.status = :finishedStatus THEN 1 ELSE 0 END) as completedGames',
            ])
            ->where('qs.deletedAt IS NULL')
            ->groupBy('qs.gameMode')
            ->setParameter('finishedStatus', 'FINISHED');
        $statsByMode = $qb->getQuery()->getResult();

        // Statistiques globales tous modes confondus
        $globalQb = $this->createQueryBuilder('qs')
            ->select([
                'COUNT(qs.id) as totalGamesAllModes',
                'COUNT(DISTINCT qs.user) as totalPlayersAllModes',
                'AVG(qs.score) as averageScoreAllModes',
                'MAX(qs.score) as highestScoreAllModes',
                'SUM(CASE WHEN qs.status = :finishedStatus THEN 1 ELSE 0 END) as completedGamesAllModes',
            ])
            ->where('qs.deletedAt IS NULL')
            ->setParameter('finishedStatus', 'FINISHED');
        $globalStats = $globalQb->getQuery()->getOneOrNullResult();

        return [
            'global' => $globalStats,
            'byMode' => $statsByMode,
        ];
    }

    /**
     * Fetches detailed statistics for a user.
     *
     * @param int $userId The user ID
     *
     * @return ?array<string, mixed>
     */
    public function getUserStats(int $userId): ?array
    {
        // Vérifier si l'utilisateur existe et a joué des parties
        $userGamesCount = $this->createQueryBuilder('qs')
            ->select('COUNT(qs.id)')
            ->where('qs.user = :userId')
            ->andWhere('qs.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        if (0 == $userGamesCount) {
            return null;
        }

        // Statistiques générales de l'utilisateur
        $userStats = $this->createQueryBuilder('qs')
            ->select([
                'u.userName',
                'u.id as userId',
                'COUNT(qs.id) as totalGames',
                'COUNT(CASE WHEN qs.status = :finishedStatus THEN 1 ELSE 0 END) as completedGames',
                'AVG(qs.score) as averageScore',
                'MAX(qs.score) as bestScore',
                'MIN(qs.score) as worstScore',
            ])
            ->leftJoin('qs.user', 'u')
            ->where('qs.user = :userId')
            ->andWhere('qs.deletedAt IS NULL')
            ->groupBy('u.id')
            ->setParameter('userId', $userId)
            ->setParameter('finishedStatus', 'FINISHED')
            ->getQuery()
            ->getOneOrNullResult();

        // Statistiques par mode de jeu
        $statsByMode = $this->createQueryBuilder('qs')
            ->select([
                'qs.gameMode',
                'COUNT(qs.id) as gamesCount',
                'COUNT(CASE WHEN qs.status = :finishedStatus THEN 1 ELSE 0 END) as completedGames',
                'AVG(qs.score) as averageScore',
                'MAX(qs.score) as bestScore',
            ])
            ->where('qs.user = :userId')
            ->andWhere('qs.deletedAt IS NULL')
            ->groupBy('qs.gameMode')
            ->setParameter('userId', $userId)
            ->setParameter('finishedStatus', 'FINISHED')
            ->getQuery()
            ->getResult();

        // Dernières parties
        $recentGames = $this->createQueryBuilder('qs')
            ->select([
                'qs.id',
                'qs.score',
                'qs.gameMode',
                'qs.status',
                'qs.finishedAt',
                'qs.startedAt',
                'c.name as categoryName',
            ])
            ->leftJoin('qs.category', 'c')
            ->where('qs.user = :userId')
            ->andWhere('qs.deletedAt IS NULL')
            ->orderBy('qs.createdAt', 'DESC')
            ->setMaxResults(10)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();

        return [
            'user'        => $userStats,
            'byMode'      => $statsByMode,
            'recentGames' => $recentGames,
        ];
    }

    /**
     * Fetches a user's ranking for a game mode.
     */
    public function getUserRanking(int $userId, GameMode $gameMode): ?int
    {
        // Récupérer le meilleur score de l'utilisateur pour ce mode
        $userBestScore = $this->createQueryBuilder('qs')
            ->select('MAX(qs.score)')
            ->where('qs.user = :userId')
            ->andWhere('qs.gameMode = :gameMode')
            ->andWhere('qs.status = :finishedStatus')
            ->andWhere('qs.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('gameMode', $gameMode->value)
            ->setParameter('finishedStatus', 'FINISHED')
            ->getQuery()
            ->getSingleScalarResult();

        if (!$userBestScore) {
            return null;
        }

        // Compter combien d'utilisateurs ont un meilleur score
        $betterScoresCount = $this->createQueryBuilder('qs')
            ->select('COUNT(DISTINCT qs.user)')
            ->where('qs.gameMode = :gameMode')
            ->andWhere('qs.status = :finishedStatus')
            ->andWhere('qs.deletedAt IS NULL')
            ->andWhere('qs.score > :userScore')
            ->setParameter('gameMode', $gameMode->value)
            ->setParameter('finishedStatus', 'FINISHED')
            ->setParameter('userScore', $userBestScore)
            ->getQuery()
            ->getSingleScalarResult();

        return $betterScoresCount + 1;
    }

    /**
     * Fetches recent best performances.
     *
     * @return array<string, mixed>
     */
    public function getRecentBestPerformances(int $limit = 10): array
    {
        return $this->createQueryBuilder('qs')
            ->select([
                'qs.id',
                'qs.score',
                'qs.pseudo',
                'qs.gameMode',
                'qs.finishedAt',
                'u.userName',
                'c.name as categoryName',
            ])
            ->leftJoin('qs.user', 'u')
            ->leftJoin('qs.category', 'c')
            ->where('qs.status = :finishedStatus')
            ->andWhere('qs.deletedAt IS NULL')
            ->andWhere('qs.finishedAt >= :lastWeek')
            ->orderBy('qs.score', 'DESC')
            ->addOrderBy('qs.finishedAt', 'DESC')
            ->setParameter('finishedStatus', 'FINISHED')
            ->setParameter('lastWeek', new \DateTime('-7 days'))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
