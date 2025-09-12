<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\DTO\AIQuizDTO;
use App\Form\AIQuizFormType;
use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    '/quiz/ai/configure',
    name: 'app_quiz_ai_config',
    methods: ['GET', 'POST']
)]
class AIConfigureController extends AbstractController
{
    public function __invoke(
        Request $request,
        SessionManager $sessionManager,
    ): Response {
        $aiQuizDto = new AIQuizDTO();
        $form      = $this->createForm(AIQuizFormType::class, $aiQuizDto);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sessionManager->setAIQuizConfiguration($aiQuizDto);

            // Redirect to the play action, following the PRG pattern
            return $this->redirectToRoute('app_quiz_ai_play');
        }

        return $this->render('quiz/ai_quiz.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
