<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Entity\User;
use App\Quiz\Service\QuizConfigurationService;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(
    '/quiz/play/classic',
    name: 'app_quiz_play_classic',
    methods: ['GET']
)]
class PlayClassicController extends AbstractController
{
    public function __invoke(
        RequestStack $requestStack,
        QuizConfigurationService $quizConfigService,
        QuestionRepository $questionRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
    ): Response {
        $session = $requestStack->getSession();
        /** @var ?User $user */
        $user = $this->getUser();

        // Récupérer la configuration et la supprimer de la session
        // @TODO Supprimer la session
        $quizDto = $quizConfigService
            ->fromSession($session)
            // ->clearSession($session)
            ->build();

        if (!$quizDto) {
            $this->addFlash('error', 'Configuration de quiz inexistante. Veuillez recommencer.');

            return $this->redirectToRoute('app_quiz_configure');
        }

        // Créer et persister la session de quiz
        $quizSession = new QuizSession();
        if ($user) {
            $quizSession->setUser($user);
        }
        $quizSession->setStartedAt(new \DateTime());
        $quizSession->setPseudo($quizDto->pseudo);
        $quizSession->setGameMode($quizDto->gameMode);
        $quizSession->setScore(0);
        $quizSession->setStatus('in_progress');

        $entityManager->persist($quizSession);
        $entityManager->flush();

        $limit     = $quizDto->gameMode->getQuestionLimit();
        $questions = $questionRepository->findQuestionsForQuiz($quizDto, $limit);

        try {
            // Sérialise les questions en JSON avec le groupe 'quiz_question'
            // @phpstan-ignore-next-line
            $questionsArray = $serializer->normalize($questions, 'json', [
                'groups' => ['quiz:question:read'],
            ]);
        } catch (ExceptionInterface $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_home');
        }

        shuffle($questionsArray);

        return $this->render('quiz/play_classic.html.twig', [
            'questions'     => $questionsArray,
            'quizSessionId' => $quizSession->getId(),
        ]);
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
