<?php

declare(strict_types=1);

namespace App\Controller\Quiz;

use App\Quiz\Service\QuizConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
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
        RequestStack $requestStack,
        QuizConfigurationService $quizConfigService,
    ): Response {
        $session = $requestStack->getSession();

        try {
            // Récupérer la configuration sans supprimer la session
            $quizDto = $quizConfigService
                ->fromSession($session)
                // ->clearSession($session)
                ->build();

            if (!$quizDto) {
                $this->addFlash(
                    'error',
                    'La configuration du quiz est introuvable. Veuillez recommencer.'
                );

                return $this->redirectToRoute('app_quiz_configure');
            }
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
