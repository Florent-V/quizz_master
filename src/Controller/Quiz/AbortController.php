<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Entity\User;
use App\Quiz\Service\QuizSessionGuard;
use App\Quiz\Service\QuizSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/{id}/abort',
    name: 'app_quiz_abort',
    requirements: [
        'id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
    ],
    methods: ['GET']
)]
class AbortController extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        QuizSessionGuard $quizSessionGuard,
        QuizSessionService $quizSessionService,
    ): Response {
        /** @var ?User $user */
        $user = $this->getUser();
        $quizSessionGuard->guardUserOwnsSession($quizSession, $user);
        $quizSessionGuard->guardSessionIsAlreadyDone($quizSession);
        $quizSessionService->cancelQuizSession($quizSession);

        return $this->redirectToRoute('app_home');
    }
}
