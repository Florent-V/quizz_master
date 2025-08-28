<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Exception\InvalidQuizConfigurationException;
use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

#[Route(
    '/quiz/play',
    name: 'app_quiz_play',
    methods: ['GET']
)]
class PlayController extends AbstractController
{
    public function __invoke(
        SessionManager $sessionManager,
    ): Response {
        try {
            $quizDto = $sessionManager->getQuizConfigurationDto();

            // Redirection dynamique en fonction du mode de jeu
            return $this->redirectToRoute(match ($quizDto->gameMode->value) {
                '20Q'          => 'app_quiz_play_classic',
                'SUDDEN_DEATH' => 'app_quiz_play_sudden_death',
                default        => throw new \RuntimeException('Unsupported game mode'),
            });
        } catch (InvalidQuizConfigurationException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        } catch (ExceptionInterface $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_home');
        }
    }

    //    #[Route('/quiz/{id}/finish', name: 'app_quiz_finish', methods: ['GET'])]
    //    public function finish(
    //        QuizSession $quizSession,
    //        EntityManagerInterface $entityManager,
    //    ): Response {
    //        // Security check
    //        if ($this->getUser() !== $quizSession->getUser()) {
    //            $this->addFlash('error', 'Vous n\'êtes pas autorisé à terminer ce quiz.');
    //
    //            return $this->redirectToRoute('app_home');
    //        }
    //
    //        // Prevent re-finishing
    //        if ('completed' === $quizSession->getStatus()) {
    //            return $this->redirectToRoute('app_quiz_results', ['id' => $quizSession->getId()]);
    //        }
    //
    //        // TODO: Add logic to check if all questions have been answered based on game mode.
    //        // For now, we assume the frontend redirects only when the game is over.
    //
    //        $quizSession->setStatus('completed');
    //        $quizSession->setFinishedAt(new \DateTime());
    //        $entityManager->flush();
    //
    //        return $this->redirectToRoute('app_quiz_results', ['id' => $quizSession->getId()]);
    //    }

    //    #[Route('/quiz/results/{id}', name: 'app_quiz_results', methods: ['GET'])]
    //    public function results(
    //        QuizSession $quizSession,
    //    ): Response {
    //        // Security checks
    //        if ($this->getUser() !== $quizSession->getUser()) {
    //            $this->addFlash('error', 'Vous n\'êtes pas autorisé à voir ces résultats.');
    //
    //            return $this->redirectToRoute('app_home');
    //        }
    //
    //        // Ensure the quiz has been completed
    //        if ('completed' !== $quizSession->getStatus()) {
    //            $this->addFlash('warning', 'Ce quiz n\'est pas encore terminé.');
    //
    //            return $this->redirectToRoute('app_home');
    //        }
    //
    //        return $this->render('quiz/results.html.twig', [
    //            'quizSession' => $quizSession,
    //        ]);
    //    }
    //
    //    #[Route('/quiz/{id}/abort', name: 'app_quiz_abort', methods: ['POST'])]
    //    public function abort(
    //        QuizSession $quizSession,
    //        EntityManagerInterface $entityManager,
    //    ): Response {
    //        // Security check: only the user who started the quiz can abort it.
    //        // Guests (user is null) can abort their own quizzes.
    //        if ($quizSession->getUser() !== $this->getUser()) {
    //            return $this->json(
    //                [
    //                    'error' => 'Vous n\'êtes pas autorisé à abandonner ce quiz.',
    //                ],
    //                Response::HTTP_FORBIDDEN
    //            );
    //        }
    //
    //        // Prevent aborting a quiz that is already finished or aborted.
    //        if (in_array($quizSession->getStatus(), ['completed', 'aborted'])) {
    //            return $this->json(['message' => 'Ce quiz est déjà terminé ou abandonné.'], Response::HTTP_OK);
    //        }
    //
    //        $quizSession->setStatus('aborted');
    //        $quizSession->setFinishedAt(new \DateTime());
    //        $entityManager->flush();
    //
    //        return $this->json(['message' => 'Quiz abandonné avec succès.'], Response::HTTP_OK);
    //    }
}
