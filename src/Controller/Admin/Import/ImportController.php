<?php

declare(strict_types=1);

namespace App\Controller\Admin\Import;

use App\Form\QuizImportFormType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/admin/quiz/import',
    name: 'admin_quiz_import',
    methods: ['GET']
)]
class ImportController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(QuizImportFormType::class);

        $importSummary = null;
        // Check if import summary is passed via query parameter after a redirect
        $encodedSummary = $request->query->get('summary');
        if ($encodedSummary) {
            try {
                $importSummary = json_decode(base64_decode($encodedSummary), true);
            } catch (\Exception $e) {
                $this->logger->error('Failed to decode import summary from URL.', ['exception' => $e]);
                $this->addFlash('error', $this->translator->trans('flash.error.invalid_summary_data'));
            }
        }

        return $this->render('admin/import/index.html.twig', [
            'form'          => $form->createView(),
            'importSummary' => $importSummary,
        ]);
    }
}
