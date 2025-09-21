<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Service\QuizConfigurationService;
use App\Quiz\Service\QuizQuestionService;
use App\Quiz\Service\QuizSessionService;
use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/play/classic',
    name: 'app_quiz_play_classic',
    methods: ['GET']
)]
class PlayClassicController extends AbstractController
{
    public function __invoke(
        QuizSessionService $quizService,
        QuizQuestionService $quizQuestionService,
        QuizConfigurationService $quizConfigurationService,
        SessionManager $sessionManager,
    ): Response {
        try {
            $quizDto         = $sessionManager->getQuizConfigurationDto();
            $hydratedQuizDto = $quizConfigurationService->buildHydratedDto($quizDto);
            // Créer et persister la session de quiz
            $quizSession = $quizService->createQuizSession($hydratedQuizDto);

            return $this->render('quiz/play_classic.html.twig', [
                'quizSessionId' => $quizSession->getId(),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        } finally {
            $sessionManager->clear('quiz');
        }
    }
}
