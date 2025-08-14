<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\GameMode;
use App\Repository\CategoryRepository;
use App\Repository\DifficultyRepository;
use App\Service\QuizConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuizController extends AbstractController
{
    #[Route('/quiz/configure', name: 'app_quiz_configure')]
    public function configure(): Response
    {
        return $this->render('quiz/configure.html.twig', [
            'currentStep' => 1,
        ]);
    }

    #[Route('/quiz/play', name: 'app_quiz_play', methods: ['GET'])]
    public function play(
        RequestStack $requestStack,
        QuizConfigurationService $quizConfigService,
    ): Response {
        $session = $requestStack->getSession();

        try {
            $quizDto = $quizConfigService->buildFromSession($session);

            if (!$quizDto) {
                $this->addFlash('error', 'La configuration du quiz est introuvable. Veuillez recommencer.');

                return $this->redirectToRoute('app_quiz_configure');
            }
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        }

        return $this->render('quiz/play.html.twig', [
            'quizConfiguration' => $quizDto,
            'currentStep'       => 2,
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
            'currentStep' => 3,
        ]);
    }
}
