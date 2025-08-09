<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Repository\QuestionRepository;
use App\Service\Admin\QuestionFieldsConfigurationService;
use App\Service\QuestionService;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<Question>
 */
class QuestionCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly QuestionService $questionService,
        private readonly QuestionRepository $questionRepository,
        private readonly QuestionFieldsConfigurationService $fieldsService,
    ) {
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        // Si on est dans l'action personnalisée, utiliser directement la méthode du repository
        if ('incompleteQuestions' === $this->getContext()->getRequest()->query->get('action')) {
            // Directement retourner le QueryBuilder de votre méthode de repository
            return $this->questionRepository->buildQueryBuilderForProposalCountNotEqualTo(4);
        }

        // Comportement par défaut
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
    }

    public static function getEntityFqcn(): string
    {
        return Question::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCommonCrud($crud, 'Question', 'Questions')
            ->setPageTitle('edit', fn (Question $question) => sprintf('Modifier « %s »', $question->getContent()))
            ->setPageTitle('detail', fn (Question $question) => sprintf('Détails de « %s »', $question->getContent()))
            ->setSearchFields(['content', 'explanation', 'category.name', 'difficulty.name'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setHelp('index', $this->getIndexHelp())
            ->setHelp('new', 'Créez une nouvelle question.')
            ->setHelp('edit', 'Modifiez la question.')
            ->setHelp('detail', 'Vue détaillée de la question.')
            ->addFormTheme('@A2lixTranslationForm/bootstrap_5_layout.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $restoreAction             = $this->buildRestoreAction();
        $duplicateAction           = $this->buildDuplicateAction();
        $manageProposalsAction     = $this->buildManageProposalsAction();
        $previewAction             = $this->buildPreviewAction();
        $statsAction               = $this->buildStatsAction();
        $incompleteQuestionsAction = $this->buildIncompleteQuestionsAction();
        $sanitizeQuestionAction    = $this->buildSanitizeAction();

        return $this->configureCommonActions($actions)
            // --- Page INDEX ---
            ->update(
                Crud::PAGE_INDEX,
                Action::DETAIL,
                fn (Action $action) => $action->displayIf(fn (Question $q) => null === $q->getDeletedAt())
            )
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                fn (Action $action) => $action->displayIf(fn (Question $q) => null === $q->getDeletedAt())
            )
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_INDEX, $manageProposalsAction)
            ->add(Crud::PAGE_INDEX, $previewAction)
            ->add(Crud::PAGE_INDEX, $statsAction)
            ->add(Crud::PAGE_INDEX, $incompleteQuestionsAction)
            ->add(Crud::PAGE_INDEX, $sanitizeQuestionAction)
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                fn (Action $action) => $action->displayIf(fn (Question $q) => null === $q->getDeletedAt())
            )
            ->reorder(
                Crud::PAGE_INDEX,
                [Action::EDIT, Action::DETAIL, Action::DELETE, 'manageProposals', 'duplicate', 'restore']
            )

            // --- Page DETAIL ---
            ->add(Crud::PAGE_DETAIL, $duplicateAction)
            ->add(Crud::PAGE_DETAIL, $manageProposalsAction)
            ->add(Crud::PAGE_DETAIL, $previewAction)

            // --- Page NEW ---
            // --- Page EDIT ---
            ->add(Crud::PAGE_EDIT, $previewAction)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('content', 'Contenu'))
            ->add(EntityFilter::new('category', 'Catégorie'))
            ->add(EntityFilter::new('difficulty', 'Difficulté'))
            ->add(BooleanFilter::new('deletedAt', 'Supprimé')
                ->setFormTypeOptions([
                    'expanded' => false,
                    'choices'  => [
                        'Actif'    => false,
                        'Supprimé' => true,
                    ],
                ]))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'))
            ->add(DateTimeFilter::new('updatedAt', 'Dernière modification'));
    }

    public function configureFields(string $pageName): iterable
    {
        return $this->fieldsService->getFieldsForPage($pageName, $this->getContext());
    }

    // === ACTIONS PERSONNALISÉES ===
    private function buildRestoreAction(): Action
    {
        return Action::new('restore', 'Restaurer', 'fas fa-undo')
            ->linkToCrudAction('restoreEntity')
            ->displayIf(fn (Question $q) => null !== $q->getDeletedAt());
    }

    private function buildDuplicateAction(): Action
    {
        return Action::new('duplicate', 'Dupliquer', 'fas fa-copy')
            ->linkToCrudAction('duplicateEntity')
            ->setCssClass('btn btn-info btn-sm')
            ->displayIf(fn (Question $q) => null === $q->getDeletedAt());
    }

    private function buildManageProposalsAction(): Action
    {
        return Action::new('manageProposals', 'Gérer les propositions', 'fa fa-list')
            ->linkToUrl(function (Question $q) {
                return $this->adminUrlGenerator
                    ->setController('App\Controller\Admin\ProposalCrudController')
                    ->setAction(Action::INDEX)
                    ->set('filters[question][comparison]', '=')
                    ->set('filters[question][value]', $q->getId())
                    ->generateUrl();
            })
            ->setCssClass('btn btn-info')
            ->displayIf(fn (Question $q) => null === $q->getDeletedAt());
    }

    private function buildPreviewAction(): Action
    {
        return Action::new('preview', 'Aperçu', 'fa fa-eye')
            ->linkToCrudAction('previewQuestion')
            ->setCssClass('btn btn-outline-primary')
            ->displayIf(fn (Question $q) => null === $q->getDeletedAt());
    }

    private function buildStatsAction(): Action
    {
        return Action::new('stats', 'Statistiques', 'fas fa-chart-bar')
            ->linkToCrudAction('showStats')
            ->setCssClass('btn btn-outline-warning')
            ->createAsGlobalAction();
    }

    private function buildSanitizeAction(): Action
    {
        return Action::new('sanitizeQuestions', 'Sanitiser', 'fa-solid fa-heart-pulse')
            ->linkToCrudAction('sanitizeQuestions')
            ->setCssClass('btn btn-danger')
            ->createAsGlobalAction();
    }

    private function buildIncompleteQuestionsAction(): Action
    {
        return Action::new('incompleteQuestions', 'Questions incomplètes', 'fas fa-exclamation-triangle')
            ->linkToCrudAction('showIncompleteQuestions')
            ->setCssClass('btn btn-warning')
            ->createAsGlobalAction();
    }

    // === MÉTHODES D'ACTION ===
    public function restoreEntity(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de restaurer : ID manquant.');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        $this->executeWithErrorHandling(
            fn () => $this->questionService->restore((int) $entityId),
            'La question a été restaurée avec succès.',
            'Erreur lors de la restauration de la question.'
        );

        return $this->redirectToIndex($this->adminUrlGenerator);
    }

    public function duplicateEntity(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de dupliquer : ID manquant.');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        $duplicate = $this->executeWithErrorHandling(
            fn () => $this->questionService->duplicate((int) $entityId),
            'Question dupliquée avec succès.',
            'Erreur lors de la duplication de la question.'
        );

        if ($duplicate) {
            return $this->redirectToEdit($this->adminUrlGenerator, $duplicate->getId());
        }

        return $this->redirectToIndex($this->adminUrlGenerator);
    }

    public function previewQuestion(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');
        if (!$entityId) {
            $this->addFlash('danger', 'Impossible de prévisualiser : ID manquant.');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        $question = $this->questionRepository->find($entityId);
        if (!$question) {
            $this->addErrorFlash('Question non trouvée');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        return $this->render('admin/question/preview.html.twig', [
            'question' => $question,
        ]);
    }

    public function showIncompleteQuestions(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->set('action', 'incompleteQuestions')
            ->generateUrl();

        return $this->redirect($url);
    }

    public function showStats(): Response
    {
        $stats = $this->executeWithErrorHandling(
            fn () => $this->questionService->getDataForStats(),
            'Statistiques récupérée avec succès.',
            'Erreur lors de la récupération des statistiques :'
        );

        if (!$stats) {
            $this->addFlash('warning', 'Aucune donnée disponible pour les statistiques.');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        return $this->render('admin/question/questions_stats.html.twig', [
            'stats' => $stats,
        ]);
    }

    public function sanitizeQuestions(AdminContext $context): Response
    {
        try {
            $report = $this->questionService->getDataForSanitizeReport();

            if (empty($report['invalidQuestions'])) {
                $this->addSuccessFlash(
                    'Contrôle qualité terminé : toutes les %count% questions ' .
                    'respectent les critères (4 propositions avec 1 seule bonne réponse) ✅',
                    ['%count%' => (string) $report['validCount']]
                );

                return $this->redirectToIndex($this->adminUrlGenerator);
            }

            $this->addInfoFlash(
                'Contrôle qualité terminé : %invalid% question(s) ne respectent pas ' .
                'les critères sur %total% questions analysées',
                [
                    '%invalid%' => (string) count($report['invalidQuestions']),
                    '%total%'   => (string) $report['totalCount'],
                ]
            );

            return $this->render('admin/question/sanitize_results.html.twig', [
                'invalidQuestions' => $report['invalidQuestions'],
                'validCount'       => $report['validCount'],
                'totalCount'       => $report['totalCount'],
            ]);
        } catch (\Exception $e) {
            $this->addFlash(
                'danger',
                'Erreur lors de la génération du rapport de questions : ' . $e->getMessage()
            );

            return $this->redirectToIndex($this->adminUrlGenerator);
        }
    }

    private function getIndexHelp(): string
    {
        return 'Vous pouvez gérer les questions ici.' .
            'Utilisez les actions pour restaurer ou dupliquer des questions supprimées.';
    }
}
