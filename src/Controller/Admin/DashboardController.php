<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Difficulty;
use App\Entity\Proposal;
use App\Entity\Question;
use App\Entity\QuizSession;
use App\Entity\QuizSessionAnswer;
use App\Entity\User;
use App\Enum\Role;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted(Role::ADMIN->value)]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addWebpackEncoreEntry('admin');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Quiz Master Admin')
            // you can include HTML contents too (e.g. to link to an image)
            // ->setTitle('<img src="..."> Quiz Master <span class="text-small">Admin</span>')
            // by default EasyAdmin displays a black square as its logo;
            // if you want to display a custom logo, internal representation of this logo
            // is a string (e.g. a path to an image file or a CSS class name)
            // ->setFaviconPath('favicon.svg')
            // ->setLogoPath('my-logo.png')
            // the domain used by default is 'messages'
            ->setTranslationDomain('admin') // Assurez-vous d'avoir un domaine de traduction 'admin'
            ->setTextDirection('ltr')
            ->setFaviconPath('build/images/icons/favicon.ico')
            // set this option if you prefer the page content to span the entire
            // browser width, instead of the default design which sets a max width
            ->renderContentMaximized()
        ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Quiz Management', 'fas fa-graduation-cap');
        yield MenuItem::subMenu('Content', 'fas fa-book')->setSubItems([
            MenuItem::linkToCrud('Categories', 'fas fa-sitemap', Category::class),
            MenuItem::linkToCrud('Difficulties', 'fas fa-signal', Difficulty::class),
            MenuItem::linkToCrud('Questions', 'fas fa-question-circle', Question::class),
            MenuItem::linkToCrud('Proposals', 'fas fa-lightbulb', Proposal::class),
            // ->setHelp('Manage individual proposals (usually managed via Questions)')
            // Removed setHelp() as it's not available here
        ]);

        yield MenuItem::section('User Activity', 'fas fa-chart-line');
        yield MenuItem::subMenu('Sessions', 'fas fa-history')->setSubItems([
            MenuItem::linkToCrud('Quiz Sessions', 'fas fa-play-circle', QuizSession::class),
            MenuItem::linkToCrud('Session Answers', 'fas fa-tasks', QuizSessionAnswer::class),
        ]);

        yield MenuItem::section('Autres');
        // Suppose que vous avez une route 'app_home'
        yield MenuItem::linkToUrl('Visiter le site', 'fas fa-globe', $this->generateUrl('app_home'));

        yield MenuItem::section('Utilitaires');
        yield MenuItem::linkToRoute(
            'Gestion des catégories',
            'fas fa-tools',
            'admin_cat_utility_index',
            []
        );
        yield MenuItem::linkToRoute(
            'Gestion des catégories V2',
            'fas fa-tools',
            'admin_utility_categories',
            []
        );

        // User management - accessible by SUPER_ADMIN
        if ($this->isGranted(Role::SUPER_ADMIN->value)) {
            yield MenuItem::section('Administration', 'fas fa-cogs');
            yield MenuItem::linkToCrud('Users', 'fas fa-users', User::class);
        } elseif ($this->isGranted(Role::ADMIN->value)) {
            // If a regular ADMIN should see users but with restrictions,
            // that logic would be in UserCrudController or by defining a separate CRUD for their view.
            // For now, only SUPER_ADMIN sees the direct User CRUD link.
            // Alternatively, show a restricted view:
            // yield MenuItem::linkToCrud('My Profile', 'fas fa-user-edit', User::class)
            // ->setAction('edit')->setEntityId($this->getUser()?->getId()); // Example
        }

        yield MenuItem::section('Links');
        // Assurez-vous que 'app_home' est le nom de votre route principale
        yield MenuItem::linkToRoute('Back to Site', 'fa fa-arrow-left', 'app_home');
    }

    /**
     * Configure le menu utilisateur en haut à droite.
     */
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Assurez-vous que votre entité User a une méthode __toString()
        // ou des méthodes comme getFullName() ou getAvatar()
        return parent::configureUserMenu($user)
            ->setName($user->getUserIdentifier())
            ->addMenuItems([
                // Suppose une route 'app_profile'
                MenuItem::linkToRoute('Mon Profil', 'fa fa-id-card', 'app_profile'),
                MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out'),
            ]);
    }
}
