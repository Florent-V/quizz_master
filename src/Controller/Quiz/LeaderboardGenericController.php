<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Enum\GameMode;
use App\Quiz\Service\LeaderboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    '/leaderboard',
    name: 'app_leaderboard',
    methods: ['GET']
)]
class LeaderboardGenericController extends AbstractController
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $gameMode = $request->query->get('mode');
        $page     = max(1, (int) $request->query->get('page', '1'));
        $limit    = 20; // Nombre d'entrées par page

        // Validation du mode de jeu
        if ($gameMode && !in_array($gameMode, array_column(GameMode::cases(), 'value'))) {
            $gameMode = null;
        }

        $gameModeEnum = $gameMode ? GameMode::from($gameMode) : null;

        // Récupération des données du leaderboard
        $leaderboardData = $this->leaderboardService->getLeaderboardData($gameModeEnum, $page, $limit);

        // Récupération des statistiques globales
        $globalStats = $this->leaderboardService->getGlobalStats();

        // Récupération du top 3 pour chaque mode de jeu actif
        $podiumData      = [];
        $modeValueToEnum = [];
        foreach (GameMode::cases() as $mode) {
            $modeValueToEnum[$mode->value] = $mode;
            if ($mode->isActive()) {
                $podiumData[$mode->value] = $this->leaderboardService->getPodiumData($mode); // Utilise $mode comme clé
            }
        }

        return $this->render('quiz/leaderboard/index.html.twig', [
            'leaderboardData' => $leaderboardData,
            'globalStats'     => $globalStats,
            'podiumData'      => $podiumData,
            'modeValueToEnum' => $modeValueToEnum,
            'currentGameMode' => $gameModeEnum,
            'currentPage'     => $page,
            'gameModes'       => GameMode::cases(),
            'activeGameModes' => array_filter(GameMode::cases(), fn ($mode) => $mode->isActive()),
        ]);
    }
}
