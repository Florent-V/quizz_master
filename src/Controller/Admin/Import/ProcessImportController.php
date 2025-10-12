<?php

declare(strict_types=1);

namespace App\Controller\Admin\Import;

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

        $overallSummary = [
            'categories_created'   => 0,
            'categories_updated'   => 0,
            'questions_created'    => 0,
            'proposals_created'    => 0,
            'difficulties_created' => 0,
            'errors'               => 0,
            'error_messages'       => [],
        ];

        foreach ($jsonFiles as $jsonFile) {
            $this->processSingleFile($jsonFile, $overallSummary);
        }

        $this->handleImportSummaryMessages($overallSummary);

        return $this->redirectWithSummary($overallSummary);
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

    /**
     * Process JSON files.
     *
     * @param array{
     *   categories_created: int,
     *   categories_updated: int,
     *   questions_created: int,
     *   proposals_created: int,
     *   difficulties_created: int,
     *   errors: int,
     *   error_messages: string[]
     * } $overallSummary
     */
    private function processSingleFile(UploadedFile $jsonFile, array &$overallSummary): void
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
     *
     * @param array{
     *   categories_created: int,
     *   categories_updated: int,
     *   questions_created: int,
     *   proposals_created: int,
     *   difficulties_created: int,
     *   errors: int,
     *   error_messages: string[]
     * } $overallSummary
     */
    private function logAndAddFileReadError(UploadedFile $jsonFile, array &$overallSummary): void
    {
        $this->addFlash(
            'error',
            $this->translator->trans(
                'flash.error.cannot_read_file',
                ['%file%' => $jsonFile->getClientOriginalName()]
            )
        );
        $this->logger->error('Failed to read uploaded JSON file.', ['filepath' => $jsonFile->getPathname()]);
        ++$overallSummary['errors'];
        $overallSummary['error_messages'][] = sprintf(
            'Cannot read file: %s',
            $jsonFile->getClientOriginalName()
        );
    }

    /**
     * Import the content of a single JSON file and update the overall summary.
     *
     * @param array{
     *   categories_created: int,
     *   categories_updated: int,
     *   questions_created: int,
     *   proposals_created: int,
     *   difficulties_created: int,
     *   errors: int,
     *   error_messages: string[]
     * } $overallSummary
     */
    private function importFileContent(UploadedFile $jsonFile, string $jsonContent, array &$overallSummary): void
    {
        try {
            $importSummary = $this->quizImporterService->importFromJson($jsonContent);
            $this->mergeImportSummaries($overallSummary, $importSummary);
        } catch (\Exception $e) {
            $this->handleImportException($e, $jsonFile);
            $this->mergeImportSummaries($overallSummary, $this->quizImporterService->importStats);
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

    /**
     * Process JSON files.
     *
     * @param array{
     *   categories_created: int,
     *   categories_updated: int,
     *   questions_created: int,
     *   proposals_created: int,
     *   difficulties_created: int,
     *   errors: int,
     *   error_messages: string[]
     * } $overallSummary
     * @param array{
     *    categories_created: int,
     *    categories_updated: int,
     *    questions_created: int,
     *    proposals_created: int,
     *    difficulties_created: int,
     *    errors: int,
     *    error_messages: string[]
     *  } $importSummary
     */
    private function mergeImportSummaries(array &$overallSummary, array $importSummary): void
    {
        foreach ($importSummary as $key => $value) {
            if (is_int($value)) {
                $overallSummary[$key] += $value;
            } elseif (is_array($value)) {
                $overallSummary[$key] = array_merge($overallSummary[$key], $value);
            }
        }
    }
}
