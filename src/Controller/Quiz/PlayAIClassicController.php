<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Enum\GameMode;
use App\Quiz\Service\QuizConfigurationService;
use App\Quiz\Service\QuizQuestionService;
use App\Quiz\Service\QuizSessionService;
use App\Quiz\Service\SessionManager;
use App\Service\AIQuizGeneratorService;
use App\Service\AIQuizImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    '/quiz/ai/play',
    name: 'app_quiz_ai_play',
    methods: ['GET']
)]
class PlayAIClassicController extends AbstractController
{
    public function __invoke(
        AIQuizGeneratorService $quizGeneratorService,
        AIQuizImportService $quizImportService,
        QuizSessionService $quizService,
        QuizQuestionService $quizQuestionService,
        QuizConfigurationService $quizConfigurationService,
        SessionManager $sessionManager,
    ): Response {
        try {
            $aiQuizDto       = $sessionManager->getAIQuizConfiguration();
            $aiGeneratedData = $quizGeneratorService->generateQuestions($aiQuizDto);

            $quizData = $quizImportService->persistQuestions($aiGeneratedData, $aiQuizDto);

            $quizDto = $quizConfigurationService->createValidatedDto(
                $quizData['category'],
                $quizData['subCategory'],
                [$aiQuizDto->difficulty->getId()],
                GameMode::TwentyQuestions,
                'test'
            );
            $hydratedQuizDto = $quizConfigurationService->buildHydratedDto($quizDto);
            $quizSession     = $quizService->createQuizSession($hydratedQuizDto);

            // If there are no questions in session (e.g., page reload), redirect to the form.
            if (empty($quizData['questions'])) {
                $this->addFlash('warning', 'Aucune questions à jouer. Veuillez changer de thème.');

                return $this->redirectToRoute('app_quiz_ai_config');
            }

            $questions = $quizQuestionService->normalizeQuizQuestions($quizData['questions']);

            return $this->render('quiz/play_classic_ia.html.twig', [
                'questions'     => $questions,
                'quizSessionId' => $quizSession->getId(),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_ai_config');
        }
    }
}
