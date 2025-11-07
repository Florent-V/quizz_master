<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\QuizSession;
use App\Enum\GameMode;
use App\Enum\QuizSessionStatus;
use App\Quiz\Service\QuizStatisticsService;
use App\Repository\QuizSessionRepository;
use App\Service\Admin\QuizSessionFieldsConfigurationService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * @extends AbstractCrudController<QuizSession>
 */
class QuizSessionCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly QuizSessionRepository $quizSessionRepository,
        private readonly QuizSessionFieldsConfigurationService $fieldsService,
        private readonly QuizStatisticsService $statisticsService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return QuizSession::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCommonCrud($crud, 'Session de Quiz', 'Sessions de Quiz')
            ->setPageTitle('edit', fn (QuizSession $session) => sprintf(
                'Session #%s - %s',
                $session->getId(),
                $session->getPseudo()
            ))
            ->setPageTitle('detail', fn (QuizSession $qs) => sprintf('Session de %s', $qs->getPseudo()))
            ->setSearchFields(['id', 'pseudo', 'user.email', 'user.username'])
            ->setDefaultSort(['startedAt' => 'DESC'])
            ->setHelp('index', $this->getIndexHelp())
            ->setHelp('detail', 'Vue détaillée de la session avec statistiques et réponses.')
            ->setPaginatorPageSize(50)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewAnswersAction  = $this->buildViewAnswersAction();
        $sessionStatsAction = $this->buildSessionStatsAction();
        $replayAction       = $this->buildReplayAction();
        $exportAction       = $this->buildExportAction();
        $globalStatsAction  = $this->buildGlobalStatsAction();

        // Keep only the detail view and the default index
        // NEW, EDIT, DELETE actions are disabled in configureCrud
        return $this->configureCommonActions($actions)
            // --- Page INDEX ---
            ->update(
                Crud::PAGE_INDEX,
                Action::DETAIL,
                fn (Action $action) => $action->displayIf(fn (QuizSession $s) => null === $s->getDeletedAt())
            )
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT) // Sessions are read-only
            ->add(Crud::PAGE_INDEX, $viewAnswersAction)
            ->add(Crud::PAGE_INDEX, $sessionStatsAction)
            ->add(Crud::PAGE_INDEX, $replayAction)
            ->add(Crud::PAGE_INDEX, $exportAction)
            ->add(Crud::PAGE_INDEX, $globalStatsAction)
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                fn (Action $action) => $action->displayIf(fn (QuizSession $s) => null === $s->getDeletedAt())
            )
            ->reorder(
                Crud::PAGE_INDEX,
                [Action::DETAIL, 'viewAnswers', 'sessionStats', 'replay', Action::DELETE]
            )

            // --- Page DETAIL ---
            ->add(Crud::PAGE_DETAIL, $viewAnswersAction)
            ->add(Crud::PAGE_DETAIL, $sessionStatsAction)
            ->add(Crud::PAGE_DETAIL, $replayAction)

            // --- Page NEW ---
            // --- Page EDIT ---
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('pseudo', 'Pseudo'))
            ->add(EntityFilter::new('user', 'Utilisateur'))
            ->add(ChoiceFilter::new('gameMode', 'Mode de jeu')
                ->setChoices(GameMode::getChoices()))
            ->add(ChoiceFilter::new('status', 'Statut')
                ->setChoices(QuizSessionStatus::getChoices()))
            ->add(NumericFilter::new('score', 'Score'))
            ->add(EntityFilter::new('category', 'Catégorie'))
            ->add(EntityFilter::new('subCategory', 'Sous-catégorie'))
            ->add(EntityFilter::new('difficulties', 'Difficultés'))
            ->add(DateTimeFilter::new('startedAt', 'Commencé le'))
            ->add(DateTimeFilter::new('finishedAt', 'Terminé le'))
            ->add(
                NullFilter::new('deletedAt', 'Supprimé')
                    ->setChoiceLabels('Actif', 'Supprimé')
            )
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return $this->fieldsService->getFieldsForPage($pageName);
    }

    // === ACTIONS PERSONNALISÉES ===
    private function buildViewAnswersAction(): Action
    {
        return Action::new('viewAnswers', 'Réponses', 'fas fa-list')
            ->linkToUrl(function (QuizSession $session) {
                return $this->adminUrlGenerator
                    ->setController(QuizSessionAnswerCrudController::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[quizSession][comparison]', '=')
                    ->set('filters[quizSession][value]', $session->getId())
                    ->generateUrl();
            })
            ->setCssClass('btn btn-info btn-sm');
    }

    private function buildSessionStatsAction(): Action
    {
        return Action::new('sessionStats', 'Stats', 'fas fa-chart-bar')
            ->linkToCrudAction('showSessionStats')
            ->setCssClass('btn btn-warning btn-sm')
            ->displayIf(fn (QuizSession $s) => null !== $s->getFinishedAt());
    }

    private function buildReplayAction(): Action
    {
        return Action::new('replay', 'Rejouer', 'fas fa-play')
            ->linkToCrudAction('replaySession')
            ->setCssClass('btn btn-success btn-sm')
            ->displayIf(fn (QuizSession $s) => 'FINISHED' === $s->getStatus()?->value);
    }

    private function buildExportAction(): Action
    {
        return Action::new('export', 'Exporter', 'fas fa-download')
            ->linkToCrudAction('exportSessions')
            ->setCssClass('btn btn-secondary')
            ->createAsGlobalAction();
    }

    private function buildGlobalStatsAction(): Action
    {
        return Action::new('globalStats', 'Statistiques Globales', 'fas fa-chart-line')
            ->linkToCrudAction('showGlobalStats')
            ->setCssClass('btn btn-primary')
            ->createAsGlobalAction();
    }

    // === MÉTHODES D'ACTION ===
    public function showSessionStats(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addErrorFlash('ID de session manquant.');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        try {
            $session = $this->quizSessionRepository->find($entityId);
            if (!$session) {
                throw new \Exception('Session non trouvée');
            }

            $stats         = $this->statisticsService->getSessionStatistics($session);
            $gameModeStats = $this->statisticsService->getGameModeStatisticsForMode($session->getGameMode());

            return $this->render('admin/quiz_session/stats.html.twig', [
                'session'       => $session,
                'stats'         => $stats,
                'gameModeStats' => $gameModeStats,
            ]);
        } catch (\Exception $e) {
            $this->addErrorFlash('Erreur lors de la récupération des statistiques : ' . $e->getMessage());

            return $this->redirectToIndex($this->adminUrlGenerator);
        }
    }

    public function replaySession(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addErrorFlash('ID de session manquant.');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        // Redirection vers l'interface de jeu avec les paramètres de la session
        return $this->redirect('/quiz/replay/' . $entityId);
    }

    public function exportSessions(): Response
    {
        try {
            $data = $this->quizSessionRepository->exportToArray();

            return $this->generateCsvResponse($data, 'quiz_sessions.csv');
        } catch (\Exception $e) {
            $this->addErrorFlash('Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToIndex($this->adminUrlGenerator);
        }
    }

    public function showGlobalStats(): Response
    {
        try {
            $stats = $this->statisticsService->getGlobalStatistics();

            return $this->render('admin/quiz_session/global_stats.html.twig', [
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->addErrorFlash('Erreur lors de la récupération des statistiques : ' . $e->getMessage());

            return $this->redirectToIndex($this->adminUrlGenerator);
        }
    }

    // === MÉTHODES UTILITAIRES ===
    /**
     * Generates a CSV response from quiz session data.
     *
     * @param array<int, array{
     *     "ID": Uuid,
     *     "Pseudo": string,
     *     "Email": string,
     *     "Mode de Jeu": string,
     *     "Statut": string,
     *     "Score": int,
     *     "Catégorie": string,
     *     "Sous-catégorie": string,
     *     "Commencé le": string,
     *     "Terminé le": string,
     *     "Créé le": string
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

    private function getIndexHelp(): string
    {
        $totalSessions  = $this->quizSessionRepository->getTotalCount();
        $activeSessions = $this->quizSessionRepository->getActiveSessionsCount();
        $avgScore       = $this->quizSessionRepository->getAverageScore();

        return sprintf(
            'Gérez les sessions de quiz (%d sessions totales, %d actives). Score moyen : %.1f/100. ' .
            'Les sessions sont créées automatiquement lors du jeu.',
            $totalSessions,
            $activeSessions,
            $avgScore
        );
    }
}
