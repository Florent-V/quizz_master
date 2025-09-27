<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\Role;
use App\Quiz\Service\CounterService;
use App\Quiz\Service\QuizStatisticsService;
use App\Repository\CategoryRepository;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionRepository;
use App\Service\Admin\DashboardMenuBuilder;
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
    public function __construct(
        private readonly QuizStatisticsService $statisticsService,
        private readonly CounterService $counterService,
        private readonly QuizSessionRepository $sessionRepository,
        private readonly QuestionRepository $questionRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly DashboardMenuBuilder $menuBuilder,
    ) {
    }

    public function index(): Response
    {
        try {
            $stats = $this->buildDashboardStatistics();

            return $this->render('admin/dashboard.html.twig', [
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, afficher un dashboard basique avec message d'erreur
            return $this->render('admin/dashboard_fallback.html.twig', [
                'error'       => $e->getMessage(),
                'basic_stats' => $this->getBasicStats(),
            ]);
        }
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Quiz Admin - Tableau de Bord')
            // ->setTitle('<img src="..."> Quiz Master <span class="text-small">Admin</span>')
            // ->setFaviconPath('favicon.ico')
            // ->setFaviconPath('build/images/icons/favicon.ico')
            // ->setLogoPath('my-logo.png')
            ->generateRelativeUrls()
            ->setLocales(['fr'])
            ->setTranslationDomain('admin')
            ->setTextDirection('ltr')
            ->renderContentMaximized()
        ;
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addWebpackEncoreEntry('admin');
    }

    public function configureMenuItems(): iterable
    {
        return $this->menuBuilder->buildMenu();
    }

    /**
     * Configure le menu utilisateur en haut à droite.
     */
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName($user->getUserIdentifier())
            ->addMenuItems([
                // Suppose une route 'app_profile'
                MenuItem::linkToRoute('Mon Profil', 'fa fa-id-card', 'app_profile'),
                MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out'),
            ]);
    }

    // === MÉTHODES PRIVÉES POUR CONSTRUIRE LES STATISTIQUES ===
    /**
     * Builds statistics for the dashboard by aggregating global statistics.
     *
     * @return array<string, object>
     */
    private function buildDashboardStatistics(): array
    {
        $globalStats = $this->statisticsService->getGlobalStatistics();

        return [
            'sessions'  => $globalStats['sessions'],
            'answers'   => $globalStats['answers'],
            'scores'    => $globalStats['scores'],
            'gameModes' => $globalStats['gameModes']
                ?? $this->sessionRepository->getGameModeStats(),
            'categories' => $globalStats['categories']
                ?? $this->categoryRepository->getCategoryStats(),
            'hardestQuestions' => $globalStats['hardestQuestions']
                ?? $this->questionRepository->getHardestQuestionsStats(),
            'trends' => $globalStats['trends']
                ?? $this->sessionRepository->getTrendData(),
        ];
    }

    /**
     * Retrieves basic application statistics (counts of sessions, answers, questions, categories, and users).
     *
     * @return array{
     *     totalSessions: int,
     *     totalAnswers: int,
     *     totalQuestions: int,
     *     totalCategories: int,
     *     totalUsers: int
     * }
     */
    private function getBasicStats(): array
    {
        return [
            'totalSessions'   => $this->counterService->countAllQuizSession(),
            'totalAnswers'    => $this->counterService->countAllQuizSessionAnswers(),
            'totalQuestions'  => $this->counterService->countAllQuestions(),
            'totalCategories' => $this->counterService->countAllCategories(),
            'totalUsers'      => $this->counterService->countAllUsers(),
        ];
    }
}
