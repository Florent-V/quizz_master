<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Controller\Admin\QuizSessionCrudController;
use App\Entity\Category;
use App\Entity\Difficulty;
use App\Entity\Proposal;
use App\Entity\Question;
use App\Entity\QuizSession;
use App\Entity\QuizSessionAnswer;
use App\Entity\User;
use App\Quiz\Service\CounterService;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class DashboardMenuBuilder
{
    public function __construct(
        private CounterService $counterService,
        private AdminUrlGenerator $adminUrlGenerator,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Construit tous les MenuItems EasyAdmin.
     *
     * @return MenuItemInterface[]
     */
    public function buildMenu(): iterable
    {
        // Dashboard
        yield MenuItem::linkToDashboard('Tableau de Bord', 'fa fa-home');
        yield MenuItem::section('Quiz Management', 'fas fa-graduation-cap');
        yield MenuItem::subMenu('Content', 'fas fa-book')->setSubItems([
            MenuItem::linkToCrud('Categories', 'fas fa-sitemap', Category::class),
            MenuItem::linkToCrud('Difficulties', 'fas fa-signal', Difficulty::class),
            MenuItem::linkToCrud('Questions', 'fas fa-question-circle', Question::class),
            MenuItem::linkToCrud('Proposals', 'fas fa-lightbulb', Proposal::class),
        ]);
        yield MenuItem::section('Gestion du Contenu');
        yield MenuItem::linkToCrud('Catégories', 'fas fa-folder-open', Category::class)
            ->setBadge($this->counterService->countAllCategories(), 'info');
        yield MenuItem::linkToCrud('Difficultés', 'fas fa-arrow-up-right-dots', Difficulty::class)
            ->setBadge($this->counterService->countAllDifficulties(), 'info');
        yield MenuItem::linkToCrud('Questions', 'fas fa-question-circle', Question::class)
            ->setBadge($this->counterService->countAllQuestions(), 'info');
        yield MenuItem::linkToCrud('Propositions', 'fas fa-comment', Proposal::class)
            ->setBadge($this->counterService->countAllProposals(), 'info');
        yield MenuItem::section('Sessions & Réponses');
        yield MenuItem::linkToCrud('Sessions de Quiz', 'fas fa-gamepad', QuizSession::class)
            ->setBadge($this->counterService->countAllQuizSession(), 'success');
        yield MenuItem::linkToCrud('Réponses', 'fas fa-list', QuizSessionAnswer::class)
            ->setBadge($this->counterService->countAllQuizSessionAnswers(), 'primary');
        yield MenuItem::section('Statistiques Avancées');
        yield MenuItem::linkToRoute(
            'Stats Globales',
            'fas fa-chart-line',
            'admin_stats_tools_global'
        );
        yield MenuItem::linkToRoute(
            'Analyse des Performances',
            'fas fa-signal',
            'admin_stats_tools_performance_analysis'
        );
        yield MenuItem::linkToRoute(
            'Analyse des Questions',
            'fas fa-chart-bar',
            'admin_stats_tools_question_analytics'
        );
        yield MenuItem::section('Gestion Utilisateurs');
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-users', User::class)
            ->setBadge($this->counterService->countAllUsers(), 'warning');
        yield MenuItem::section('Outils');
        yield MenuItem::linkToRoute(
            'Outils d\'Export',
            'fas fa-download',
            'admin_stats_tools_export'
        );
        yield MenuItem::linkToRoute(
            'État du Système',
            'fas fa-heartbeat',
            'admin_stats_tools_system_health'
        );
        yield MenuItem::linkToRoute(
            'Gestion des catégories',
            'fas fa-tools',
            'admin_category_tool_index'
        );
        yield MenuItem::linkToRoute(
            'Gestion des catégories V2',
            'fas fa-tools',
            'admin_category_utility'
        );
        yield MenuItem::section(
            'Raccourcis Rapides'
        );
        yield MenuItem::linkToUrl(
            'Sessions Récentes',
            'fas fa-clock',
            $this->generateExpiredSessionUrl()
        );
        yield MenuItem::linkToUrl(
            'Sessions Échouées',
            'fas fa-exclamation-triangle',
            $this->generateFailedSessionUrl()
        );
        yield MenuItem::linkToRoute(
            'Questions Difficiles',
            'fas fa-brain',
            'admin_stats_tools_difficult_questions'
        );
        yield MenuItem::linkToRoute(
            'Problèmes de Performance',
            'fas fa-tachometer-alt',
            'admin_stats_tools_performance_issues'
        );
        yield MenuItem::section('Autres');
        yield MenuItem::linkToUrl(
            'Visiter le site',
            'fas fa-globe',
            $this->urlGenerator->generate('app_home')
        );
    }

    //    public function configureMenuItems(): iterable
    //    {
    //        // User management - accessible by SUPER_ADMIN
    //        if ($this->isGranted(Role::SUPER_ADMIN->value)) {
    //            yield MenuItem::section('Administration', 'fas fa-cogs');
    //            yield MenuItem::linkToCrud('Users', 'fas fa-users', User::class);
    //        } elseif ($this->isGranted(Role::ADMIN->value)) {
    //            // If a regular ADMIN should see users but with restrictions,
    //            // that logic would be in UserCrudController or by defining a separate CRUD for their view.
    //            // For now, only SUPER_ADMIN sees the direct User CRUD link.
    //            // Alternatively, show a restricted view:
    //            // yield MenuItem::linkToCrud('My Profile', 'fas fa-user-edit', User::class)
    //            // ->setAction('edit')->setEntityId($this->getUser()?->getId()); // Example
    //        }
    //    }

    private function generateFailedSessionUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(QuizSessionCrudController::class)
            ->setAction('index')
            ->set('filters', [
                'status' => [
                    'comparison' => '=',
                    'value'      => 'FAILED',
                ],
            ])
            ->generateUrl();
    }

    private function generateExpiredSessionUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(QuizSessionCrudController::class)
            ->setAction('index')
            ->set('sort', ['startedAt' => 'DESC'])
            ->set('perPage', 25) // attention, c’est 'perPage' en camelCase pour EasyAdmin
            ->generateUrl();
    }
}
