<?php

declare(strict_types=1);

namespace App\Controller\Admin\Import;

use App\Form\QuizImportFormType;
use App\Service\QuizImporterService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/admin/quiz/import',
    name: 'admin_quiz_import_handle',
    methods: ['POST']
)]
class ProcessImportController extends AbstractController
{
    public function __construct(
        private readonly QuizImporterService $quizImporterService,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(QuizImportFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->translator->trans('flash.error.no_file_uploaded'));

            return $this->redirectWithSummary(null);
        }

        /** @var UploadedFile|null $jsonFile */
        $jsonFile = $form->get('json_file')->getData();
        if (!$jsonFile) {
            $this->addFlash('error', $this->translator->trans('flash.error.no_file_uploaded'));

            return $this->redirectWithSummary(null);
        }

        $jsonContent = $this->readJsonFile($jsonFile);
        if (false === $jsonContent) {
            $this->addFlash('error', $this->translator->trans('flash.error.cannot_read_file'));
            $this->logger->error('Failed to read uploaded JSON file.', ['filepath' => $jsonFile->getPathname()]);

            return $this->redirectWithSummary(null);
        }

        try {
            $importSummary = $this->quizImporterService->importFromJson($jsonContent);
            $this->handleImportSummaryMessages($importSummary);
        } catch (\Exception $e) {
            $this->handleImportException($e, $jsonFile);
            $importSummary = $this->quizImporterService->importStats;
        }

        return $this->redirectWithSummary($importSummary);
    }

    private function readJsonFile(UploadedFile $jsonFile): string|false
    {
        return file_get_contents($jsonFile->getPathname());
    }

    /**
     * Handle and log exceptions during import.
     *
     * @param array{
     *   categories_created: int,
     *   categories_updated: int,
     *   questions_created: int,
     *   proposals_created: int,
     *   difficulties_created: int,
     *   errors: int,
     *   error_messages: string[]
     * }|null $importSummary
     */
    private function handleImportSummaryMessages(?array $importSummary): void
    {
        if (!$importSummary) {
            return;
        }
        if ($importSummary['errors'] > 0) {
            $this->addFlash(
                'warning',
                $this->translator->trans(
                    'flash.warning.import_with_errors',
                    ['%count%' => $importSummary['errors']]
                )
            );
            foreach ($importSummary['error_messages'] as $msg) {
                $this->addFlash('danger', $msg);
            }

            return;
        }
        $this->addFlash('success', $this->translator->trans('flash.success.import_successful'));
        $this->addFlash('info', $this->translator->trans('import.summary.details', [
            '%categories_created%'   => $importSummary['categories_created'],
            '%categories_updated%'   => $importSummary['categories_updated'],
            '%questions_created%'    => $importSummary['questions_created'],
            '%proposals_created%'    => $importSummary['proposals_created'],
            '%difficulties_created%' => $importSummary['difficulties_created'],
        ]));
    }

    private function handleImportException(\Exception $e, UploadedFile $jsonFile): void
    {
        $this->logger->error('Exception during quiz import process.', [
            'exception' => $e,
            'file'      => $jsonFile->getClientOriginalName(),
        ]);
        $this->addFlash(
            'error',
            $this->translator->trans(
                'flash.error.unexpected_import_error',
                ['%message%' => $e->getMessage()]
            )
        );
    }

    /**
     * Redirects to the import page with the import summary encoded in the URL.
     *
     * @param array{
     *   categories_created: int,
     *   categories_updated: int,
     *   questions_created: int,
     *   proposals_created: int,
     *   difficulties_created: int,
     *   errors: int,
     *   error_messages: string[]
     * }|null $importSummary
     */
    private function redirectWithSummary(?array $importSummary): Response
    {
        $encodedSummary = base64_encode(json_encode($importSummary));

        return $this->redirectToRoute('admin_quiz_import', ['summary' => $encodedSummary]);
    }
}
