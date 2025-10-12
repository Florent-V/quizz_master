<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Enum\GameMode;
use App\Enum\Role;
use App\Quiz\Service\LeaderboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(
    '/leaderboard/user/{userId}',
    name: 'app_leaderboard_user',
    methods: ['GET']
)]
#[IsGranted(Role::USER->value)]
class LeaderboardUserController extends AbstractController
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService,
    ) {
    }

    public function __invoke(int $userId): Response
    {
        $userStats = $this->leaderboardService->getUserStats($userId);

        if (!$userStats) {
            throw $this->createNotFoundException('Utilisateur non trouvé ou aucune partie jouée.');
        }

        return $this->render('quiz/leaderboard/user_stats.html.twig', [
            'userStats' => $userStats,
            'gameModes' => GameMode::cases(),
        ]);
    }
}
