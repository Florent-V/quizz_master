<?php

declare(strict_types=1);

namespace App\Controller\Admin\API;

use App\Enum\Role;
use App\Repository\QuestionRepository;
use App\Service\QuestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(
    '/admin/api/questions/{categoryId}',
    name: 'admin_api_questions',
    methods: ['GET']
)]
#[IsGranted(Role::ADMIN->value)]
class QuestionController extends AbstractController
{
    public function __construct(
        private readonly QuestionService $questionService,
    ) {
    }

    public function __invoke(
        int $categoryId,
        QuestionRepository $questionRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $data = $this->questionService->getQuestionsDataForApi($categoryId);

        if (null === $data) {
            return $this->json(['error' => 'Catégorie non trouvée'], 404);
        }

        return $this->json($data, 200, [], [
            'json_encode_options' => JSON_INVALID_UTF8_SUBSTITUTE,
        ]);

        $this->findInvalidUtf8($data);

        try {


            return $this->json($data);
        } catch (\Exception $e) {
            // Encoder avec des options pour ignorer les erreurs
            $safeJson = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            dump($safeJson);

            // Ou voir l'erreur exacte
            dump(json_last_error_msg());
            dd($data);
        }
    }

    private function findInvalidUtf8($data, $path = ''): void
    {
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                $currentPath = $path ? "$path.$key" : $key;

                if (is_string($value)) {
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        dump("❌ Invalid UTF-8 at: $currentPath");
                        dump('Value: ' . bin2hex($value)); // Affiche en hexadécimal
                        dump('Preview: ' . substr($value, 0, 100));
                    }
                } elseif (is_array($value) || is_object($value)) {
                    $this->findInvalidUtf8($value, $currentPath);
                }
            }
        }
    }
}
