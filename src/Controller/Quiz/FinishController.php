<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Entity\User;
use App\Enum\QuizSessionStatus;
use App\Quiz\Service\FinishQuiz\FinishQuizValidationService;
use App\Quiz\Service\QuizSessionGuard;
use App\Quiz\Service\QuizSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/{id}/finish',
    name: 'app_quiz_finish',
    requirements: [
        'id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
    ],
    methods: ['GET']
)]
class FinishController extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        QuizSessionGuard $quizSessionGuard,
        QuizSessionService $quizSessionService,
        FinishQuizValidationService $finishQuizValidationService,
    ): Response {
        /** @var ?User $user */
        $user = $this->getUser();
        $quizSessionGuard->guardUserOwnsSession($quizSession, $user);
        // Prevent re-finishing
        if (QuizSessionStatus::Finished === $quizSession->getStatus()) {
            return $this->redirectToRoute('app_quiz_results_v1', ['id' => $quizSession->getId()]);
        }
        $quizSessionGuard->guardSessionIsInProgress($quizSession);
        $finishQuizValidationService->validateCanFinishQuiz($quizSession);
        $quizSessionService->finishQuizSession($quizSession);

        return $this->redirectToRoute('app_quiz_results_v1', ['id' => $quizSession->getId()]);
    }
}
