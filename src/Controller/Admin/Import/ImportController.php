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

#[Route('/admin/quiz')]
class ImportController extends AbstractController
{
    //    private QuizImporterService $quizImporterService;
    //    private LoggerInterface $logger;
    //    private TranslatorInterface $translator;
    //
    //    public function __construct(
    //        QuizImporterService $quizImporterService,
    //        LoggerInterface $logger,
    //        TranslatorInterface $translator,
    //    ) {
    //        $this->quizImporterService = $quizImporterService;
    //        $this->logger              = $logger;
    //        $this->translator          = $translator;
    //    }
    //
    //    #[Route('/import', name: 'admin_quiz_import', methods: ['GET'])]
    //    public function showImportForm(Request $request): Response
    //    {
    //        // Sécurisation de la route (exemple, à adapter selon votre système de sécurité)
    //        // $this->denyAccessUnlessGranted('ROLE_ADMIN');
    //
    //        $form = $this->createForm(QuizImportFormType::class);
    //
    //        $importSummary = null;
    //        // Check if import summary is passed via query parameter after a redirect
    //        $encodedSummary = $request->query->get('summary');
    //        if ($encodedSummary) {
    //            try {
    //                $importSummary = json_decode(base64_decode($encodedSummary), true);
    //            } catch (\Exception $e) {
    //                $this->logger->error('Failed to decode import summary from URL.', ['exception' => $e]);
    //                $this->addFlash('error', $this->translator->trans('flash.error.invalid_summary_data'));
    //            }
    //        }
    //
    //        return $this->render('admin/import/index.html.twig', [
    //            'form'          => $form->createView(),
    //            'importSummary' => $importSummary,
    //        ]);
    //    }
    //
    //    #[Route('/import', name: 'admin_quiz_import_handle', methods: ['POST'])]
    //    public function handleImportSubmission(Request $request): Response
    //    {
    //        $form = $this->createForm(QuizImportFormType::class);
    //        $form->handleRequest($request);
    //
    //        $importSummary = null;
    //
    //        if ($form->isSubmitted() && $form->isValid()) {
    //            /** @var UploadedFile $jsonFile */
    //            $jsonFile = $form->get('json_file')->getData();
    //
    //            if ($jsonFile) {
    //                $jsonContent = file_get_contents($jsonFile->getPathname());
    //                if (false === $jsonContent) {
    //                    $this->addFlash('error', $this->translator->trans('flash.error.cannot_read_file'));
    //                    $this->logger->error(
    //                        'Failed to read uploaded JSON file.',
    //                        ['filepath' => $jsonFile->getPathname()]
    //                    );
    //                } else {
    //                    try {
    //                        $importSummary = $this->quizImporterService->importFromJson($jsonContent);
    //
    //                        if ($importSummary['errors'] > 0) {
    //                            $this->addFlash(
    //                                'warning',
    //                                $this->translator->trans(
    //                                    'flash.warning.import_with_errors',
    //                                    ['%count%' => $importSummary['errors']]
    //                                )
    //                            );
    //                            foreach ($importSummary['error_messages'] as $msg) {
    //                                $this->addFlash('danger', $msg);
    //                            }
    //                        } else {
    //                            $this->addFlash(
    //                                'success',
    //                                $this->translator->trans('flash.success.import_successful')
    //                            );
    //                            $this->addFlash('info', $this->translator->trans(
    //                                'import.summary.details',
    //                                [
    //                                    '%categories_created%'   => $importSummary['categories_created'],
    //                                    '%categories_updated%'   => $importSummary['categories_updated'],
    //                                    '%questions_created%'    => $importSummary['questions_created'],
    //                                    '%proposals_created%'    => $importSummary['proposals_created'],
    //                                    '%difficulties_created%' => $importSummary['difficulties_created'],
    //                                ]
    //                            ));
    //                        }
    //                    } catch (\Exception $e) {
    //                        $this->logger->error('Exception during quiz import process.', [
    //                            'exception' => $e,
    //                            'file'      => $jsonFile->getClientOriginalName(),
    //                        ]);
    //                        $this->addFlash(
    //                            'error',
    //                            $this->translator->trans(
    //                                'flash.error.unexpected_import_error',
    //                                ['%message%' => $e->getMessage()]
    //                            )
    //                        );
    //                        // Pour afficher les erreurs partielles
    //                        $importSummary = $this->quizImporterService->importStats;
    //                    }
    //                }
    //            } else {
    //                $this->addFlash('error', $this->translator->trans('flash.error.no_file_uploaded'));
    //            }
    //        }
    //
    //        // Encode the import summary and redirect to the GET route
    //        $encodedSummary = base64_encode(json_encode($importSummary));
    //
    //        return $this->redirectToRoute('admin_quiz_import', ['summary' => $encodedSummary]);
    //    }
}
