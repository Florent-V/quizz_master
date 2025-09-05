<?php

declare(strict_types=1);

namespace App\Controller\Quiz\API;

use App\Entity\QuizSession;
use App\Quiz\Service\QuizService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(
    '/quiz-sessions/{id}/next-one-question',
    name: 'app_quiz_get_next_one_question',
    methods: ['POST']
)]
class FetchNextOneQuestion extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        QuizService $quizService,
        SerializerInterface $serializer,
    ): JsonResponse {
        try {
            $quizService->checkProcessQuizSession($quizSession);
            $questions = $quizService->getNextQuestions($quizSession, 1);

            $question = $questions[0] ?? null;
            if (!$question) {
                throw $this->createNotFoundException('No valid question found.');
            }
            $quizSessionAnswer = $quizService->prepareAnswer($quizSession, $question);
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
                    'questionNumber'      => $quizService->getQuestionNumber($quizSession),
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
