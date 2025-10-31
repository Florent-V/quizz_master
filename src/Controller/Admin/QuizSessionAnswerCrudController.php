<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\QuizSessionAnswer;
use App\Quiz\Service\QuizStatisticsService;
use App\Repository\QuizSessionAnswerRepository;
use App\Service\Admin\QuizSessionAnswerFieldsConfigurationService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @extends AbstractCrudController<QuizSessionAnswer>
 */
class QuizSessionAnswerCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly QuizSessionAnswerFieldsConfigurationService $fieldsService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly QuizSessionAnswerRepository $answerRepository,
        private readonly QuizStatisticsService $statisticsService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return QuizSessionAnswer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCommonCrud($crud, 'Réponse de session', 'Réponses de session')
            ->setSearchFields(['question.content', 'proposal.content', 'quizSession.id'])
            ->setDefaultSort(['askedAt' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $answerStatsAction = $this->buildAnswerStatsAction();
        $exportAction      = $this->buildExportAction();

        // Keep only the detail view and the default index.
        return $this->configureCommonActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $answerStatsAction)
            ->add(Crud::PAGE_DETAIL, $answerStatsAction)
            ->add(Crud::PAGE_INDEX, $exportAction)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                ChoiceFilter::new('isCorrect', 'Réponse Correcte')
                    ->setChoices([
                        'Oui'    => true,
                        'Non'    => false,
                        'Aucune' => null,
                    ])
            )
            ->add(EntityFilter::new('question', 'Question'))
            ->add(NumericFilter::new('score', 'Score'))
            ->add(NumericFilter::new('time', 'Temps (sec)'))
            ->add(DateTimeFilter::new('answeredAt', 'Date de Réponse'))
            ->add(DateTimeFilter::new('askedAt', 'Date de la Question'))
            ->add(EntityFilter::new('quizSession', 'Session de Quiz'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return $this->fieldsService->getFieldsForPage($pageName);
    }

    // === ACTIONS PERSONNALISÉES ===
    private function buildAnswerStatsAction(): Action
    {
        return Action::new('answerStats', 'Statistiques', 'fas fa-chart-bar')
            ->linkToCrudAction('showAnswerStats')
            ->setCssClass('btn btn-info');
    }

    private function buildExportAction(): Action
    {
        return Action::new('export', 'Exporter', 'fas fa-download')
            ->linkToCrudAction('exportAnswers')
            ->setCssClass('btn btn-secondary')
            ->createAsGlobalAction();
    }

    /**
     * Affiche les statistiques détaillées d'une réponse.
     */
    public function showAnswerStats(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addErrorFlash('ID de réponse manquant.');

            return $this->redirectToIndexTemp();
        }

        try {
            $answer = $this->answerRepository->find($entityId);
            if (!$answer) {
                throw new \Exception('Réponse non trouvée');
            }

            $stats = $this->statisticsService->getAnswerStatistics($answer);

            return $this->render('admin/quiz_session_answer/answer_stats.html.twig', [
                'answer' => $answer,
                'stats'  => $stats,
            ]);
        } catch (\Exception $e) {
            $this->addErrorFlash('Erreur lors de la récupération des statistiques : ' . $e->getMessage());

            return $this->redirectToIndexTemp();
        }
    }

    //    /**
    //     * Exporte les réponses de session au format CSV.
    //     */
    //    #[Route('/admin/quiz-session-answers/export', name: 'admin_quiz_session_answers_export')]
    public function exportAnswers(): Response
    {
        try {
            $data = $this->answerRepository->exportToArray();

            return $this->generateCsvResponse($data, 'quiz_session_answers.csv');
        } catch (\Exception $e) {
            $this->addErrorFlash('Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToIndexTemp();
        }
    }

    /**
     * Generates a CSV response from quiz session data.
     *
     * @param array<int, array{
     *     ID: int,
     *     Session: string|null,
     *     Question: string,
     *     'Réponse choisie': string,
     *     Correcte: string,
     *     Score: int|null,
     *     'Temps (sec)': float|null,
     *     Catégorie: string,
     *     'Posée le': string,
     *     'Répondue le': string
     * }> $data The quiz session data to export
     * @param string $filename The name of the generated CSV file
     */
    private function generateCsvResponse(array $data, string $filename): Response
    {
        $tempFile = tmpfile();
        if (!empty($data)) {
            $firstRow = $data[0] ?? null;
            if (is_array($firstRow)) {
                fputcsv($tempFile, array_keys($firstRow));
                foreach ($data as $row) {
                    fputcsv($tempFile, $row);
                }
            }
        }

        rewind($tempFile);
        $csv = stream_get_contents($tempFile);
        fclose($tempFile);

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Crée une redirection vers la page d'index du contrôleur courant.
     */
    public function redirectToIndexTemp(): Response
    {
        return $this->redirect($this->adminUrlGenerator
            ->setController(static::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }
}
