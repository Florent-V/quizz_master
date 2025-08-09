<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Proposal;
use App\Service\Admin\ProposalFieldsConfigurationService;
use App\Service\ProposalService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<Proposal>
 */
class ProposalCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ProposalService $proposalService,
        private readonly ProposalFieldsConfigurationService $fieldsService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Proposal::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCommonCrud($crud, 'Proposition', 'Propositions')
            ->setPageTitle('edit', fn (Proposal $proposal) => sprintf('Modifier « %s »', $proposal->getContent()))
            ->setPageTitle('detail', fn (Proposal $proposal) => sprintf('Détails de « %s »', $proposal->getContent()))
            ->setSearchFields(['content', 'question.content'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setHelp('index', $this->getIndexHelp())
            ->setHelp('new', 'Créez une nouvelle proposition.')
            ->setHelp('edit', 'Modifiez la proposition.')
            ->setHelp('detail', 'Vue détaillée de la proposition.')
            ->addFormTheme('@A2lixTranslationForm/bootstrap_5_layout.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $restoreAction   = $this->buildRestoreAction();
        $duplicateAction = $this->buildDuplicateAction();
        $toggleAction    = $this->buildToggleAction();

        return $this->configureCommonActions($actions)
            // --- Page INDEX ---
            ->update(
                Crud::PAGE_INDEX,
                Action::DETAIL,
                fn (Action $action) => $action->displayIf(fn (Proposal $p) => null === $p->getDeletedAt())
            )
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                fn (Action $action) => $action->displayIf(fn (Proposal $p) => null === $p->getDeletedAt())
            )
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_INDEX, $toggleAction)
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                fn (Action $action) => $action->displayIf(fn (Proposal $p) => null === $p->getDeletedAt())
            )
            ->reorder(
                Crud::PAGE_INDEX,
                [Action::EDIT, Action::DETAIL, Action::DELETE, 'restore', 'toggleCorrect', 'duplicate']
            )
            // --- Page DETAIL ---
            ->add(Crud::PAGE_DETAIL, $duplicateAction)
            ->add(Crud::PAGE_DETAIL, $toggleAction)
            // --- Page NEW ---
            // --- Page EDIT ---
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('content', 'Contenu'))
            ->add(EntityFilter::new('question', 'Question'))
            ->add(BooleanFilter::new('isCorrect', 'Correcte'))
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
            ->setCssClass('btn btn-success btn-sm')
            ->displayIf(fn (Proposal $p) => null !== $p->getDeletedAt());
    }

    private function buildDuplicateAction(): Action
    {
        return Action::new('duplicate', 'Dupliquer', 'fas fa-copy')
            ->linkToCrudAction('duplicateEntity')
            ->setCssClass('btn btn-info btn-sm')
            ->displayIf(fn (Proposal $p) => null === $p->getDeletedAt());
    }

    private function buildToggleAction(): Action
    {
        return Action::new('toggleCorrect', 'Basculer correct/incorrect', 'fa fa-toggle-on')
            ->linkToCrudAction('toggleCorrect')
            ->setCssClass('btn btn-warning');
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
            fn () => $this->proposalService->restore((int) $entityId),
            'La proposition a été restaurée avec succès.',
            'Erreur lors de la restauration de la proposition.'
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
            fn () => $this->proposalService->duplicate((int) $entityId),
            'Proposition dupliquée avec succès.',
            'Erreur lors de la duplication de la proposition.'
        );

        if ($duplicate) {
            return $this->redirectToEdit($this->adminUrlGenerator, $duplicate->getId());
        }

        return $this->redirectToIndex($this->adminUrlGenerator);
    }

    public function toggleCorrect(AdminContext $context): Response
    {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            $this->addFlash('danger', 'Problème: ID manquant.');

            return $this->redirectToIndex($this->adminUrlGenerator);
        }

        $this->executeWithErrorHandling(
            fn () => $this->proposalService->toggleCorrect((int) $entityId),
            'La proposition a été modifiée avec succès.',
            'Erreur lors de la modification de la réponse.'
        );

        return $this->redirectToIndex($this->adminUrlGenerator);
    }

    public function getIndexHelp(): string
    {
        return 'Gérez les propositions des questions. ' .
            'Vous pouvez créer, modifier, supprimer ou restaurer des propositions.';
    }
}
