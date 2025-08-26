<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/quiz/summary',
    name: 'app_quiz_summary',
    methods: ['GET']
)]
class SummaryController extends AbstractController
{
    public function __invoke(
        SessionManager $sessionManager,
    ): Response {

        try {
            $quizDto = $sessionManager->getQuizConfigurationDto();
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_quiz_configure');
        }

        return $this->render('quiz/summary.html.twig', [
            'quizConfiguration' => $quizDto,
            'currentStep'       => 2,
        ]);
    }
}
