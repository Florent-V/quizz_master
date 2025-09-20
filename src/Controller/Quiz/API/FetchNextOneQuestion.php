<?php

declare(strict_types=1);

namespace App\Controller\Quiz\API;

use App\Entity\QuizSession;
use App\Quiz\Service\QuizAnswerService;
use App\Quiz\Service\QuizQuestionService;
use App\Quiz\Service\QuizSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(
    '/quiz-sessions/{id}/next-one-question',
    name: 'app_quiz_get_next_one_question',
    requirements: [
        'id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
    ],
    methods: ['POST']
)]
class FetchNextOneQuestion extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        QuizSessionService $quizService,
        QuizQuestionService $questionService,
        QuizAnswerService $quizAnswerService,
        SerializerInterface $serializer,
    ): JsonResponse {
        try {
            $quizService->checkProcessQuizSession($quizSession);
            $questions = $questionService->getNextQuestions($quizSession, 1);

            $question = $questions[0] ?? null;
            if (!$question) {
                throw $this->createNotFoundException('No valid question found.');
            }
            $quizSessionAnswer = $quizAnswerService->prepareAnswer($quizSession, $question);
            // @phpstan-ignore-next-line
            $questionData = $serializer->normalize(
                $question,
                null,
                ['groups' => ['quiz:question:read']]
            );

            return new JsonResponse(
                [
                    'question'            => $questionData,
                    'quizSessionAnswerId' => $quizSessionAnswer->getId(),
                    'questionNumber'      => $questionService->getQuestionNumber($quizSession),
                ],
                Response::HTTP_OK,
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                $e instanceof HttpException
                    ? $e->getStatusCode()
                    : Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
