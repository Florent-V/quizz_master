<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Entity\QuizSession;
use App\Quiz\Exception\InvalidAnswerException;
use App\Quiz\Exception\InvalidQuestionException;
use App\Quiz\Exception\InvalidQuizConfigurationException;
use App\Quiz\Exception\NoMoreQuestionsException;
use App\Quiz\Service\QuizAnswerService;
use App\Quiz\Service\QuizQuestionService;
use App\Quiz\Service\QuizSessionService;
use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PlaySuddenDeathController extends AbstractController
{
    public function __construct(
        private readonly QuizSessionService $quizService,
        private readonly QuizQuestionService $quizQuestionService,
        private readonly QuizAnswerService $quizAnswerService,
    ) {
    }

    #[Route('/quiz/play/sudden-death', name: 'app_quiz_play_sudden_death', methods: ['GET', 'POST'])]
    public function play(
        SessionManager $session,
    ): Response {

        try {
            $quizDto = $session->getQuizConfigurationDto();

            // Créer et persister la session de quiz
            $quizSession = $this->quizService->createQuizSession($quizDto);
            $session->setMultiple([
                'quiz_session_id'     => $quizSession->getId(),
                'quiz_session_config' => $quizDto,
                'quiz_question_index' => 0,
            ]);

            return $this->redirectToRoute('app_quiz_play_sudden_death_question');
        } catch (InvalidQuizConfigurationException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        }
    }

    #[Route('/quiz/play/sudden-death/question', name: 'app_quiz_play_sudden_death_question', methods: ['GET'])]
    public function question(
        SessionManager $session,
    ): Response {

        try {
            $quizDto       = $session->getSessionConfig();
            $quizSessionId = $session->getQuizSessionId();
            $quizSession   = $this->quizService->getQuizSession($quizSessionId);

            $question = $this->quizQuestionService->getQuizQuestion(
                $session->getKey('quiz_current_question_id'),
                $quizDto
            );
            $quizSessionAnswer = $this->quizAnswerService->prepareAnswer($quizSession, $question);

            $session->setMultiple([
                'quiz_current_question_id' => $question->getId(),
                'quiz_current_answer_id'   => $quizSessionAnswer->getId(),
            ]);

            return $this->render('quiz/play_sudden_death.html.twig', [
                'question'       => $question,
                'quizSession'    => $quizSession,
                'questionNumber' => ($session->getKey('quiz_question_index') ?? 0) + 1,
            ]);
        } catch (NoMoreQuestionsException $e) {
            // @TODO Clore le quiz
            //            $quizSession->setStatus(QuizSessionStatus::Finished);
            //            $quizSessionRepository->getEntityManager()->flush();

            $this->addFlash('info', 'Toutes les questions ont été posées, voici votre score !');

            return $this->redirectToRoute('app_quiz_results_v1', [
                'id' => $session->getKey('quiz_session_id'),
            ]);
        } catch (\Exception $e) {
            $session->clear('quiz');
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        }
    }

    #[Route('/quiz/play/sudden-death/answer', name: 'app_quiz_play_sudden_death_answer', methods: ['POST'])]
    public function answer(
        Request $request,
        SessionManager $session,
    ): Response {

        try {
            $answeredAt = new \DateTimeImmutable();
            // Session recuperation
            $quizSessionId = $session->getQuizSessionId();
            $quizSession   = $this->quizService->getQuizSession($quizSessionId);

            // User Proposal Recuperation
            $currentQuestionId = $session->getCurrentQuestionId();
            $proposalId        = $request->request->get('answer');
            $proposal          = $this->quizAnswerService->getProposal((int) $proposalId, (int) $currentQuestionId);

            $quizSessionAnswerId = $session->getSessionAnswerId();
            $quizSessionAnswer   = $this->quizAnswerService->getQuizSessionAnswer($quizSessionAnswerId);

            $this->quizAnswerService->processAnswer($quizSession, $quizSessionAnswer, $proposal, $answeredAt);

            if (!$proposal->isCorrect()) {
                $this->quizService->processEndQuizSession($quizSession);
                $session->clear('quiz');

                return $this->redirectToRoute(
                    'app_quiz_play_sudden_death_game_over',
                    ['id' => $quizSession->getId()]
                );
            }

            $session->removeKeys(['quiz_current_question_id', 'current_answer_id']);
            $session->setKey(
                'quiz_question_index',
                $session->getKey('quiz_question_index') + 1
            );

            return $this->redirectToRoute('app_quiz_play_sudden_death_question');
            // phpcs:disable PSR12.Operators.OperatorSpacing
        } catch (InvalidQuestionException|InvalidAnswerException $e) {
            // phpcs:enable
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_play_sudden_death_question');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        }
    }

    #[Route('/quiz/play/sudden-death/game-over/{id}', name: 'app_quiz_play_sudden_death_game_over')]
    public function over(QuizSession $quizSession): Response
    {
        return $this->render('quiz/over_sudden_death.html.twig', [
            'quizSession' => $quizSession,
        ]);
    }
}
