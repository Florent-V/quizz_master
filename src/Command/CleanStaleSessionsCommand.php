<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\QuizSessionStatus;
use App\Repository\QuizSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-stale-sessions',
    description: 'Supprime les sessions de quiz en cours depuis plus de 24h (sessions abandonnées)'
)]
class CleanStaleSessionsCommand extends Command
{
    private const DEFAULT_HOURS_THRESHOLD = 24;

    public function __construct(
        private readonly QuizSessionRepository $quizSessionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Affiche les sessions qui seraient supprimées sans les supprimer réellement'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force la suppression sans demander de confirmation'
            )
            ->addOption(
                'hours',
                null,
                InputOption::VALUE_REQUIRED,
                'Nombre d\'heures d\'inactivité avant suppression (par défaut: 24)',
                (string) self::DEFAULT_HOURS_THRESHOLD
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nettoyage des sessions abandonnées');

        $dryRun = (bool) $input->getOption('dry-run');
        $force  = (bool) $input->getOption('force');
        $hours  = (int) $input->getOption('hours');

        if ($hours < 1) {
            $io->error('Le nombre d\'heures doit être supérieur ou égal à 1.');

            return Command::FAILURE;
        }

        $dateLimit = $this->calculateDateLimit($hours);
        $io->info(
            "Recherche des sessions en cours depuis plus de {$hours} heure(s)" .
            " (avant le {$dateLimit->format('Y-m-d H:i:s')})..."
        );

        $staleSessions = $this->fetchStaleSessions($dateLimit);

        if (empty($staleSessions)) {
            $io->success('Aucune session abandonnée à supprimer.');

            return Command::SUCCESS;
        }

        $totalAnswers = $this->countTotalAnswers($staleSessions);
        $this->displaySessionsInfo($io, $staleSessions, $totalAnswers);

        if ($dryRun) {
            return $this->handleDryRun($io, count($staleSessions), $totalAnswers);
        }

        if (!$force && !$this->confirmDeletion($io, count($staleSessions), $totalAnswers)) {
            $io->info('Opération annulée.');

            return Command::SUCCESS;
        }

        $this->deleteSessions($io, $staleSessions);

        return Command::SUCCESS;
    }

    /**
     * Calcule la date limite en fonction du nombre d'heures.
     */
    private function calculateDateLimit(int $hours): \DateTime
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify("-{$hours} hours");

        return $dateLimit;
    }

    /**
     * Récupère les sessions abandonnées.
     *
     * @return array<int, \App\Entity\QuizSession>
     */
    private function fetchStaleSessions(\DateTime $dateLimit): array
    {
        return $this->quizSessionRepository->createQueryBuilder('qs')
            ->where('qs.status = :status')
            ->andWhere('qs.updatedAt < :dateLimit')
            ->setParameter('status', QuizSessionStatus::InProgress)
            ->setParameter('dateLimit', $dateLimit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de réponses pour toutes les sessions.
     *
     * @param array<int, \App\Entity\QuizSession> $sessions
     */
    private function countTotalAnswers(array $sessions): int
    {
        $total = 0;
        foreach ($sessions as $session) {
            $total += $session->getQuizSessionAnswers()->count();
        }

        return $total;
    }

    /**
     * Affiche les informations sur les sessions trouvées.
     *
     * @param array<int, \App\Entity\QuizSession> $sessions
     */
    private function displaySessionsInfo(SymfonyStyle $io, array $sessions, int $totalAnswers): void
    {
        $totalSessions = count($sessions);

        $io->section('Sessions trouvées');
        $io->text([
            "Sessions abandonnées trouvées : <fg=yellow>{$totalSessions}</>",
            "Réponses associées : <fg=yellow>{$totalAnswers}</>",
        ]);

        if ($io->isVerbose()) {
            $this->displaySessionsTable($io, $sessions);
        }
    }

    /**
     * Affiche un tableau détaillé des sessions.
     *
     * @param array<int, \App\Entity\QuizSession> $sessions
     */
    private function displaySessionsTable(SymfonyStyle $io, array $sessions): void
    {
        $rows = [];
        foreach ($sessions as $session) {
            $inactivityHours = $this->calculateInactivityHours($session->getUpdatedAt());
            $rows[]          = [
                $session->getId(),
                $session->getPseudo(),
                $session->getGameMode()->value,
                $session->getQuizSessionAnswers()->count(),
                $session->getUpdatedAt()?->format('Y-m-d H:i:s') ?? 'N/A',
                $inactivityHours . 'h',
            ];
        }

        $io->table(
            ['ID', 'Pseudo', 'Mode', 'Réponses', 'Dernière activité', 'Inactivité'],
            $rows
        );
    }

    /**
     * Gère le mode dry-run.
     */
    private function handleDryRun(SymfonyStyle $io, int $totalSessions, int $totalAnswers): int
    {
        $io->warning('Mode DRY-RUN : Aucune suppression ne sera effectuée.');
        $io->text("Ces {$totalSessions} session(s) et {$totalAnswers} réponse(s) seraient supprimées.");

        return Command::SUCCESS;
    }

    /**
     * Demande confirmation à l'utilisateur.
     */
    private function confirmDeletion(SymfonyStyle $io, int $totalSessions, int $totalAnswers): bool
    {
        return $io->confirm(
            "Voulez-vous vraiment supprimer {$totalSessions} session(s) abandonnée(s) et {$totalAnswers} réponse(s) ?",
            false
        );
    }

    /**
     * Supprime les sessions et affiche la progression.
     *
     * @param array<int, \App\Entity\QuizSession> $sessions
     */
    private function deleteSessions(SymfonyStyle $io, array $sessions): void
    {
        $io->section('Suppression en cours');
        $io->progressStart(count($sessions));

        $stats = $this->performDeletion($sessions, $io);

        $io->progressFinish();
        $this->displayDeletionResults($io, $stats, count($sessions));
    }

    /**
     * Effectue la suppression des sessions.
     *
     * @param array<int, \App\Entity\QuizSession> $sessions
     *
     * @return array{sessions: int, answers: int}
     */
    private function performDeletion(array $sessions, SymfonyStyle $io): array
    {
        $deletedSessions = 0;
        $deletedAnswers  = 0;

        foreach ($sessions as $session) {
            try {
                $answersCount = $session->getQuizSessionAnswers()->count();

                $this->entityManager->remove($session);
                $this->entityManager->flush();

                ++$deletedSessions;
                $deletedAnswers += $answersCount;

                $io->progressAdvance();
            } catch (\Exception $e) {
                $io->error("Erreur lors de la suppression de la session {$session->getId()}: {$e->getMessage()}");
            }
        }

        return ['sessions' => $deletedSessions, 'answers' => $deletedAnswers];
    }

    /**
     * Affiche les résultats de la suppression.
     *
     * @param array{sessions: int, answers: int} $stats
     */
    private function displayDeletionResults(SymfonyStyle $io, array $stats, int $totalSessions): void
    {
        $io->success([
            'Nettoyage terminé avec succès !',
            "Sessions supprimées : {$stats['sessions']}/{$totalSessions}",
            "Réponses supprimées : {$stats['answers']}",
        ]);
    }

    /**
     * Calcule le nombre d'heures d'inactivité depuis une date.
     */
    private function calculateInactivityHours(?\DateTime $lastActivity): int
    {
        if (null === $lastActivity) {
            return 0;
        }

        $now  = new \DateTime();
        $diff = $now->getTimestamp() - $lastActivity->getTimestamp();

        return (int) floor($diff / 3600);
    }
}
