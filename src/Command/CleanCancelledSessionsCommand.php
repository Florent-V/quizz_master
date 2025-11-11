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
    name: 'app:clean-cancelled-sessions',
    description: 'Supprime les sessions de quiz annulées et leurs réponses associées'
)]
class CleanCancelledSessionsCommand extends Command
{
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
                'older-than',
                null,
                InputOption::VALUE_REQUIRED,
                'Supprime uniquement les sessions annulées plus anciennes que X jours (ex: 30)',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nettoyage des sessions annulées');

        $dryRun    = (bool) $input->getOption('dry-run');
        $force     = (bool) $input->getOption('force');
        $olderThan = $input->getOption('older-than');

        $cancelledSessions = $this->fetchCancelledSessions($olderThan, $io);

        if (empty($cancelledSessions)) {
            $io->success('Aucune session annulée à supprimer.');

            return Command::SUCCESS;
        }

        $totalAnswers = $this->countTotalAnswers($cancelledSessions);
        $this->displaySessionsInfo($io, $cancelledSessions, $totalAnswers);

        if ($dryRun) {
            return $this->handleDryRun($io, count($cancelledSessions), $totalAnswers);
        }

        if (!$force && !$this->confirmDeletion($io, count($cancelledSessions), $totalAnswers)) {
            $io->info('Opération annulée.');

            return Command::SUCCESS;
        }

        $this->deleteSessions($io, $cancelledSessions);

        return Command::SUCCESS;
    }

    /**
     * Récupère les sessions annulées selon les critères.
     *
     * @return array<int, \App\Entity\QuizSession>
     */
    private function fetchCancelledSessions(?string $olderThan, SymfonyStyle $io): array
    {
        $qb = $this->quizSessionRepository->createQueryBuilder('qs')
            ->where('qs.status = :status')
            ->setParameter('status', QuizSessionStatus::Cancelled);

        if (null !== $olderThan) {
            $this->applyDateFilter($qb, (int) $olderThan);
            $io->info("Recherche des sessions annulées datant de plus de {$olderThan} jours...");

            return $qb->getQuery()->getResult();
        }

        $io->info('Recherche de toutes les sessions annulées...');

        return $qb->getQuery()->getResult();
    }

    /**
     * Applique un filtre de date au QueryBuilder.
     */
    private function applyDateFilter(\Doctrine\ORM\QueryBuilder $qb, int $days): void
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify("-{$days} days");

        $qb->andWhere('qs.updatedAt < :dateLimit')
            ->setParameter('dateLimit', $dateLimit);
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
            "Sessions annulées trouvées : <fg=yellow>{$totalSessions}</>",
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
            $rows[] = [
                $session->getId(),
                $session->getPseudo(),
                $session->getGameMode()->value,
                $session->getQuizSessionAnswers()->count(),
                $session->getUpdatedAt()?->format('Y-m-d H:i:s') ?? 'N/A',
            ];
        }

        $io->table(
            ['ID', 'Pseudo', 'Mode', 'Réponses', 'Dernière mise à jour'],
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
            "Voulez-vous vraiment supprimer {$totalSessions} session(s) et {$totalAnswers} réponse(s) ?",
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
}
