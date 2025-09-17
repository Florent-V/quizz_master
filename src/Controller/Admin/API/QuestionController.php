<?php

declare(strict_types=1);

namespace App\Controller\Admin\API;

use App\Service\QuestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/admin/api/questions/{categoryId}',
    name: 'admin_api_questions',
    methods: ['GET']
)]
class QuestionController extends AbstractController
{
    public function __construct(
        private readonly QuestionService $questionService,
    ) {
    }

    public function __invoke(int $categoryId): Response
    {
        $data = $this->questionService->getQuestionsDataForApi($categoryId);

        if (null === $data) {
            return $this->json(['error' => 'Catégorie non trouvée'], 404);
        }

        return $this->json($data);
    }
}
