<?php

declare(strict_types=1);

namespace App\Controller\Admin\Import;

use App\DTO\ImportSummaryDto;
use App\Form\QuizImportFormType;
use App\Quiz\Service\Import\QuizImporterService;
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

        /** @var UploadedFile[]|null $jsonFiles */
        $jsonFiles = $form->get('json_file')->getData();
        if (!$jsonFiles || 0 === count($jsonFiles)) {
            $this->addFlash('error', $this->translator->trans('flash.error.no_file_uploaded'));

            return $this->redirectWithSummary(null);
        }

        $overallSummary = new ImportSummaryDto();

        foreach ($jsonFiles as $jsonFile) {
            $this->processSingleFile($jsonFile, $overallSummary);
        }

        $this->handleImportSummaryMessages($overallSummary);

        return $this->redirectWithSummary($overallSummary);
    }

    /**
     * Handle and log exceptions during import.
     */
    private function handleImportSummaryMessages(?ImportSummaryDto $importSummary): void
    {
        if (!$importSummary) {
            return;
        }
        if ($importSummary->errors > 0) {
            $this->addFlash(
                'warning',
                $this->translator->trans(
                    'flash.warning.import_with_errors',
                    ['%count%' => $importSummary->errors]
                )
            );
            foreach ($importSummary->errorMessages as $msg) {
                $this->addFlash('danger', $msg);
            }

            return;
        }
        $this->addFlash('success', $this->translator->trans('flash.success.import_successful'));
        $this->addFlash('info', $this->translator->trans('import.summary.details', [
            '%categories_created%'   => $importSummary->categoriesCreated,
            '%categories_updated%'   => $importSummary->categoriesUpdated,
            '%questions_created%'    => $importSummary->questionsCreated,
            '%proposals_created%'    => $importSummary->proposalsCreated,
            '%difficulties_created%' => $importSummary->difficultiesCreated,
        ]));
    }

    /**
     * Process JSON files.
     */
    private function processSingleFile(UploadedFile $jsonFile, ImportSummaryDto $overallSummary): void
    {
        $jsonContent = $this->quizImporterService->readJsonFile($jsonFile);
        if (false === $jsonContent) {
            $this->logAndAddFileReadError($jsonFile, $overallSummary);

            return;
        }

        $this->importFileContent($jsonFile, $jsonContent, $overallSummary);
    }

    /**
     * Log and add flash message for file read error.
     */
    private function logAndAddFileReadError(UploadedFile $jsonFile, ImportSummaryDto $overallSummary): void
    {
        $this->addFlash(
            'error',
            $this->translator->trans(
                'flash.error.cannot_read_file',
                ['%file%' => $jsonFile->getClientOriginalName()]
            )
        );
        $this->logger->error('Failed to read uploaded JSON file.', ['filepath' => $jsonFile->getPathname()]);
        ++$overallSummary->errors;
        $overallSummary->errorMessages[] = sprintf(
            'Cannot read file: %s',
            $jsonFile->getClientOriginalName()
        );
    }

    /**
     * Import the content of a single JSON file and update the overall summary.
     */
    private function importFileContent(
        UploadedFile $jsonFile,
        string $jsonContent,
        ImportSummaryDto $overallSummary,
    ): void {
        try {
            $importSummary = $this->quizImporterService->importFromJson($jsonContent);
            $overallSummary->merge($importSummary);
        } catch (\Exception $e) {
            $this->handleImportException($e, $jsonFile);
            // En cas d'exception, on crée un ImportSummary avec l'erreur
            $errorSummary = new ImportSummaryDto();
            ++$errorSummary->errors;
            $errorSummary->errorMessages[] = $e->getMessage();
            $overallSummary->merge($errorSummary);
        }
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
     */
    private function redirectWithSummary(?ImportSummaryDto $importSummary): Response
    {
        $encodedSummary = base64_encode(json_encode($importSummary));

        return $this->redirectToRoute('admin_quiz_import', ['summary' => $encodedSummary]);
    }
}
