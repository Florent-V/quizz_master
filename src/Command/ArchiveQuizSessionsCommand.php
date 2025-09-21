<?php

namespace App\Command;

use App\Enum\QuizSessionStatus;
use App\Repository\QuizSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:archive-quiz-sessions',
    description: 'Archives quiz sessions that are older than 24 hours and still in progress.',
)]
class ArchiveQuizSessionsCommand extends Command
{
    public function __construct(
        private readonly QuizSessionRepository $quizSessionRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to archive old quiz sessions.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $staleSessions = $this->quizSessionRepository->findStaleInProgressSessions();

        $count = count($staleSessions);

        if (0 === $count) {
            $io->info('No stale quiz sessions to archive.');

            return Command::SUCCESS;
        }

        foreach ($staleSessions as $session) {
            $session->setStatus(QuizSessionStatus::Cancelled);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d quiz session(s) have been archived.', $count));

        return Command::SUCCESS;
    }
}
