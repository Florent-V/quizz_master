<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Service\QuizConfigurationService;
use App\Quiz\Service\QuizSessionService;
use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    '/quiz/play/sudden-death',
    name: 'app_quiz_play_sudden_death',
    methods: ['GET']
)]
final class PlaySuddenDeathController extends AbstractController
{
    public function __invoke(
        SessionManager $session,
        QuizSessionService $quizService,
        QuizConfigurationService $quizConfigurationService,
    ): Response {
        try {
            $quizDto         = $session->getQuizConfigurationDto();
            $hydratedQuizDto = $quizConfigurationService->buildHydratedDto($quizDto);
            // Créer et persister la session de quiz
            $quizSession = $quizService->createQuizSession($hydratedQuizDto);

            return $this->render('quiz/play_sudden_death.html.twig', [
                'quizSessionId' => $quizSession->getId(),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        } finally {
            $session->clear('quiz');
        }
    }
}
