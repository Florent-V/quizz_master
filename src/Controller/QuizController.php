<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\GameMode;
use App\Repository\CategoryRepository;
use App\Repository\DifficultyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuizController extends AbstractController
{
    #[Route('/quiz/configure', name: 'app_quiz_configure')]
    public function configure(): Response
    {
        return $this->render('quiz/configure.html.twig');
    }

    #[Route('/quiz/play', name: 'app_quiz_play')]
    public function play(Request $request): Response
    {
        // Récupérer les données depuis les paramètres de requête
        $categoryId      = $request->query->get('category');
        $subCategoryId   = $request->query->get('subCategory');
        $difficultiesIds = $request->query->get('difficulties'); // String séparée par des virgules
        $gameMode        = $request->query->get('gameMode');

        // Validation basique (optionnelle)
        if (!$difficultiesIds || !$gameMode) {
            $this->addFlash('error', 'Configuration incomplète');

            return $this->redirectToRoute('quiz_configure');
        }

        // Convertir les IDs de difficultés en tableau
        $difficultiesArray = explode(',', $difficultiesIds);

        // Démarrer le quiz avec cette configuration
        return $this->render('quiz/play.html.twig', [
            'categoryId'      => $categoryId,
            'subCategoryId'   => $subCategoryId,
            'difficultiesIds' => $difficultiesArray,
            'gameMode'        => $gameMode,
        ]);
    }

    #[Route('/game/summary', name: 'app_game_summary', methods: ['GET'])]
    public function gameSummary(
        CategoryRepository $categoryRepository,
        DifficultyRepository $difficultyRepository,
    ): Response {

        $category = $categoryRepository->findOneBy([
            'slug' => 'animaux',
        ]);
        $subCategory = $categoryRepository->findOneBy([
            'slug' => 'races-de-chien',
        ]);
        $difficulty = $difficultyRepository->findOneBy([
            'id' => 4,
        ]);

        $modeLabel = GameMode::TwentyQuestions->getLabel();

        //        dd($category, $subCategory, $difficulty, $modeLabel);

        return $this->render('quiz/game_summary.html.twig', [
            'category'    => $category,
            'subCategory' => $subCategory,
            'difficulty'  => $difficulty,
            'mode'        => $modeLabel,
            'guestPseudo' => 'Pseudo de l\'invité',
        ]);
    }
}
