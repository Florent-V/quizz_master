<?php

declare(strict_types=1);

namespace App\Controller\Quiz\API;

use App\Entity\QuizSession;
use App\Quiz\Service\QuizQuestionService;
use App\Quiz\Service\QuizSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(
    '/api/quiz-session/{id}/next-questions',
    name: 'app_quiz_get_next_questions',
    requirements: [
        'id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
    ],
    methods: ['GET']
)]
class FetchNextQuestions extends AbstractController
{
    public function __invoke(
        QuizSession $quizSession,
        Request $request,
        QuizSessionService $quizService,
        QuizQuestionService $questionService,
        SerializerInterface $serializer,
    ): JsonResponse {
        $quizService->checkProcessQuizSession($quizSession);
        $limit     = (int) $request->query->get('limit', '1');
        $questions = $questionService->getNextQuestions($quizSession, $limit);

        $questionsData = $serializer->serialize(
            $questions,
            'json',
            ['groups' => ['quiz:question:read']]
        );

        return new JsonResponse($questionsData, Response::HTTP_OK, [], true);
    }
}
