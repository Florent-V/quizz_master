<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

trait AdminCrudControllerTrait
{
    /**
     * Crée une redirection vers la page d'index du contrôleur courant.
     */
    protected function redirectToIndex(AdminUrlGenerator $urlGenerator): Response
    {
        return $this->redirect($urlGenerator
            ->setController(static::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    /**
     * Crée une redirection vers la page d'edition du contrôleur courant.
     */
    protected function redirectToEdit(AdminUrlGenerator $urlGenerator, int $entityId): Response
    {
        return $this->redirect($urlGenerator
            ->setController(static::class)
            ->setAction(Action::EDIT)
            ->setEntityId($entityId)
            ->generateUrl());
    }

    /**
     * Configuration CRUD commune.
     */
    protected function configureCommonCrud(Crud $crud, string $singularLabel, string $pluralLabel): object
    {
        return $crud
            ->setEntityLabelInSingular($singularLabel)
            ->setEntityLabelInPlural($pluralLabel)
            ->setPageTitle('index', 'Gestion des %entity_label_plural%')
            ->setPageTitle('new', 'Créer une nouvelle %entity_label_singular%')
            ->setPageTitle('edit', 'Modifier : %entity_label%')
            ->setPageTitle('detail', 'Détail : %entity_label%')
            ->setDateTimeFormat('dd/MM/yyyy HH:mm')
            ->setPaginatorPageSize(25)
            ->setPaginatorRangeSize(4)
            ->showEntityActionsInlined(false)
            ->setAutofocusSearch()
            ->setTimezone('Europe/Paris')
            ->setDateTimeFormat('short', 'short');
    }

    /**
     * Configure les actions communes pour tous les contrôleurs admin.
     */
    protected function configureCommonActions(Actions $actions): Actions
    {
        return $actions
            // --- Common Actions for Page INDEX ---
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                fn (Action $action) => $action
                    ->setIcon('fa fa-pencil')
                    ->setLabel('Modifier')
            )
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(
                Crud::PAGE_INDEX,
                Action::DETAIL,
                fn (Action $action) => $action
                    ->setIcon('fa fa-eye')
                    ->setLabel('Détails')
            )
            ->update(
                Crud::PAGE_INDEX,
                Action::DELETE,
                fn (Action $action) => $action->setIcon('fa fa-trash')->setLabel('Supprimer')
            )

            // --- Common Actions for Page NEW ---
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->update(
                Crud::PAGE_NEW,
                Action::SAVE_AND_RETURN,
                fn (Action $action) => $action->setLabel('Créer')->setIcon('fas fa-plus')
            )
            ->add(Crud::PAGE_NEW, Action::SAVE_AND_CONTINUE)
            ->update(
                Crud::PAGE_NEW,
                Action::SAVE_AND_CONTINUE,
                fn (Action $action) => $action->setLabel('Créer et continuer')
                ->setIcon('fas fa-plus')
                ->setCssClass('btn btn-outline-success')
            )
            ->update(
                Crud::PAGE_NEW,
                Action::SAVE_AND_ADD_ANOTHER,
                fn (Action $action) => $action->setLabel('Créer et ajouter un nouvel élement')
                ->setIcon('fas fa-plus')
                ->setCssClass('btn btn-outline-success')
            )
            ->reorder(
                Crud::PAGE_NEW,
                [Action::INDEX, Action::SAVE_AND_CONTINUE, Action::SAVE_AND_ADD_ANOTHER, Action::SAVE_AND_RETURN]
            )

            // --- Common Actions for Page EDIT ---
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->update(
                Crud::PAGE_EDIT,
                Action::SAVE_AND_RETURN,
                fn (Action $action) => $action->setLabel('Enregistrer')->setIcon('fa fa-save')
            )
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE, fn (Action $action) => $action
                ->setLabel('Enregistrer et continuer')
                ->setIcon('fas fa-save')
                ->setCssClass('btn btn-outline-success'))
        ;
    }

    /**
     * Ajoute un flash message avec un format standardisé.
     *
     * @param array<string, string> $parameters
     */
    protected function addSuccessFlash(string $message, array $parameters = []): void
    {
        $this->addFlash('success', $this->formatFlashMessage($message, $parameters));
    }

    /**
     * Ajoute un flash message d'erreur avec un format standardisé.
     *
     * @param array<string, string> $parameters
     */
    protected function addErrorFlash(string $message, array $parameters = []): void
    {
        $this->addFlash('danger', $this->formatFlashMessage($message, $parameters));
    }

    /**
     * Ajoute un flash message d'information avec un format standardisé.
     *
     * @param array<string, string> $parameters
     */
    protected function addInfoFlash(string $message, array $parameters = []): void
    {
        $this->addFlash('info', $this->formatFlashMessage($message, $parameters));
    }

    /**
     * Formate un message flash avec des paramètres.
     *
     * @param array<string, string> $parameters
     */
    private function formatFlashMessage(string $message, array $parameters = []): string
    {
        if (empty($parameters)) {
            return $message;
        }

        return strtr($message, $parameters);
    }

    /**
     * Exécute une action avec gestion d'erreur standardisée.
     */
    protected function executeWithErrorHandling(callable $action, string $successMessage, string $errorMessage): mixed
    {
        $result = '';
        try {
            $result = $action();
            $this->addSuccessFlash($successMessage);
        } catch (\Exception $e) {
            $this->addErrorFlash($errorMessage . $e->getMessage());

            // Log l'erreur si un logger est disponible
            if (property_exists($this, 'logger') && $this->logger) {
                $this->logger->error('Admin action failed', [
                    'exception' => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]);
            }
        } finally {
            return $result;
        }
    }

    /**
     * Configuration commune des QueryBuilder pour les entités avec SoftDelete.
     */
    protected function configureQueryBuilderForSoftDelete(QueryBuilder $queryBuilder): object
    {
        // Désactiver le filtre SoftDeleteable pour voir toutes les entités
        $queryBuilder->getEntityManager()->getFilters()->disable('softdeleteable');

        return $queryBuilder;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Désactiver le filtre SoftDelete pour voir toutes les entités
        $queryBuilder->getEntityManager()->getFilters()->disable('softdeleteable');

        return $queryBuilder;
    }

    /**
     * Récupère une entité depuis le contexte admin avec gestion des erreurs.
     *
     * @template T of object
     *
     * @param class-string<T> $entityClass
     *
     * @return T|null
     */
    protected function getEntityFromContext(
        AdminContext $context,
        EntityManagerInterface $em,
        string $entityClass,
    ): ?object {
        $entityId = $context->getRequest()->query->get('entityId');

        if (!$entityId) {
            return null;
        }

        $em->getFilters()->disable('softdeleteable');
        $entity = $em->getRepository($entityClass)->find($entityId);
        $em->getFilters()->enable('softdeleteable');

        return $entity instanceof $entityClass ? $entity : null;
    }
}
