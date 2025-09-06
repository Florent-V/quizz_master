<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Exception\InvalidQuizConfigurationException;
use App\Quiz\Exception\NoMoreQuestionsException;
use App\Quiz\Service\QuizConfigurationService;
use App\Quiz\Service\QuizService;
use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/play/time-attack',
    name: 'app_quiz_play_time_attack',
    methods: ['GET']
)]
class PlayTimeAttackController extends AbstractController
{
    /**
     * @throws NoMoreQuestionsException
     */
    public function __invoke(
        QuizService $quizService,
        QuizConfigurationService $quizConfigurationService,
        SessionManager $sessionManager,
    ): Response {
        try {
            $quizDto = $sessionManager->getQuizConfigurationDto();
            $quizDto = $quizConfigurationService->retrieveData($quizDto);
            // Créer et persister la session de quiz
            $quizSession    = $quizService->createQuizSession($quizDto);
            $questionsArray = $quizService->getQuestionsForRelativeSession($quizSession);

            return $this->render('quiz/play_time_attack.html.twig', [
                'questions'     => $questionsArray,
                'quizSessionId' => $quizSession->getId(),
            ]);
        } catch (InvalidQuizConfigurationException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_home');
        }
    }
}
