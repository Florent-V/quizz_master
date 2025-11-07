<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\Role;
use App\Service\MoveQuestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(
    '/admin-tool/move-question/',
    name: 'admin_move_question',
    methods: ['POST']
)]
#[IsGranted(Role::ADMIN->value)]
class MoveQuestionController extends AbstractController
{
    public function __construct(
        private readonly MoveQuestionService $moveQuestionService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $sourceId    = (int) $request->request->get('source_category');
        $targetId    = (int) $request->request->get('target_category');
        $questionIds = $request->request->all('question_ids');

        try {
            $this->moveQuestionService->validateMoveQuestionsParam($sourceId, $targetId, $questionIds);
            $movedCount = $this->moveQuestionService->moveQuestions($questionIds, (int) $sourceId, (int) $targetId);

            $this->addFlash('success', sprintf(
                '%d question(s) déplacée(s) avec succès.',
                $movedCount
            ));
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors du déplacement : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_category_utility_1');
    }
}
