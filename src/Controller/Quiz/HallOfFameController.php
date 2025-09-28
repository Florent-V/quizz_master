<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Enum\GameMode;
use App\Repository\QuizSessionLeaderBoardRepository;
use App\Repository\QuizSessionRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    '/hall-of-fame',
    name: 'app_hall_of_fame',
    methods: ['GET']
)]
class HallOfFameController extends AbstractController
{
    public function __invoke(
        QuizSessionRepository $quizSessionRepository,
        QuizSessionLeaderBoardRepository $leaderBoardRepository,
        Request $request,
        PaginatorInterface $paginator,
    ): Response {
        $gameModes         = array_filter(GameMode::cases(), static fn (GameMode $mode) => $mode->isActive());
        $selectedModeValue = $request->query->get('mode', reset($gameModes)->value);
        $selectedMode      = GameMode::tryFrom($selectedModeValue) ?? reset($gameModes);

        // Get Top 3 for the podium
        $topThree = $leaderBoardRepository->findTopThreeByGameMode($selectedMode);

        // Get paginated list for the rest
        $excludedIds  = array_map(static fn ($score) => $score['id'], $topThree);
        $queryBuilder = $leaderBoardRepository->createHallOfFameQueryBuilder($selectedMode, $excludedIds);

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10 // items per page
        );

        return $this->render('quiz/hall_of_fame/index.html.twig', [
            'gameModes'    => $gameModes,
            'selectedMode' => $selectedMode,
            'topThree'     => $topThree,
            'pagination'   => $pagination,
        ]);
    }
}
