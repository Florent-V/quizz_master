<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\QuizSession;
use App\Service\Admin\QuizSessionFieldsConfigurationService;
// use App\Quiz\Service\QuizSessionService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<QuizSession>
 */
class QuizSessionCrudController extends AbstractCrudController
{
    use AdminCrudControllerTrait;

    public function __construct(
        private readonly QuizSessionFieldsConfigurationService $fieldsService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        // private readonly QuizSessionService $quizSessionService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return QuizSession::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCommonCrud($crud, 'Quiz Session', 'Quiz Sessions')
            ->setSearchFields(['user.email', 'user.userName', 'score'])
            ->setDefaultSort(['startedAt' => 'DESC'])
            // Sessions are generally created by the app, not by the admin
            ->disable(Action::NEW, Action::EDIT, Action::DELETE) // This can be adjusted if needed
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return $this->fieldsService->getFieldsForPage($pageName);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Keep only the detail view and the default index
        // NEW, EDIT, DELETE actions are disabled in configureCrud
        return $this->configureCommonActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function redirectToIndex(): Response
    {
        return $this->redirect($this->adminUrlGenerator
            ->setController(static::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }
}
