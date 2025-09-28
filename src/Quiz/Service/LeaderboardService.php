<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\Enum\GameMode;
use App\Repository\QuizSessionLeaderBoardRepository;

readonly class LeaderboardService
{
    public function __construct(
        private QuizSessionLeaderBoardRepository $leaderBoardRepository,
    ) {
    }

    /**
     * Récupère les données du leaderboard avec pagination.
     *
     * @return array<string, mixed>
     */
    public function getLeaderboardData(?GameMode $gameMode = null, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        return $this->leaderBoardRepository->getLeaderboardData($gameMode, $limit, $offset);
    }

    /**
     * Récupère les données du podium (top 3) pour un mode de jeu.
     *
     * @return array<string, mixed>
     */
    public function getPodiumData(GameMode $gameMode): array
    {
        return $this->leaderBoardRepository->getPodiumData($gameMode);
    }

    /**
     * Récupère les statistiques globales de l'application.
     *
     * @return array<string, mixed>
     */
    public function getGlobalStats(): array
    {
        return $this->leaderBoardRepository->getGlobalStats();
    }

    /**
     * Récupère les statistiques détaillées d'un utilisateur.
     *
     * @return ?array<string, mixed>
     */
    public function getUserStats(int $userId): ?array
    {
        return $this->leaderBoardRepository->getUserStats($userId);
    }

    /**
     * Récupère le classement d'un utilisateur pour un mode de jeu.
     */
    public function getUserRanking(int $userId, GameMode $gameMode): ?int
    {
        return $this->leaderBoardRepository->getUserRanking($userId, $gameMode);
    }

    /**
     * Récupère les meilleures performances récentes.
     *
     * @return array<string, mixed>
     */
    public function getRecentBestPerformances(int $limit = 10): array
    {
        return $this->leaderBoardRepository->getRecentBestPerformances($limit);
    }
}
