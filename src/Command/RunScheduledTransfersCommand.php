<?php

namespace App\Command;

use App\Service\Transfer\N8nScheduledTransferDispatcher;
use App\Service\Transfer\ScheduledTransferEngineResolver;
use App\Service\Transfer\ScheduledTransferExecutionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:transfers:run-scheduled', description: 'Executes due scheduled transfers')]
final class RunScheduledTransfersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ScheduledTransferEngineResolver $engineResolver,
        private ScheduledTransferExecutionService $executionService,
        private N8nScheduledTransferDispatcher $n8nDispatcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = $this->fetchDueRows();

        if ($rows === []) {
            $io->success('No due scheduled transfers.');

            return Command::SUCCESS;
        }

        if ($this->engineResolver->isN8n()) {
            try {
                $summary = $this->n8nDispatcher->dispatchDueTransfers($rows);
                $io->success(sprintf(
                    'n8n dispatch done. sent=%d failed=%d webhookStatus=%s',
                    $summary['sent'],
                    $summary['failed'],
                    $summary['webhook_status'] !== null ? (string) $summary['webhook_status'] : 'n/a'
                ));

                return Command::SUCCESS;
            } catch (\Throwable $e) {
                $io->error('n8n dispatch failed: ' . $e->getMessage());

                return Command::FAILURE;
            }
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $row) {
            $scheduledId = (int) ($row['id'] ?? 0);

            try {
                $this->executionService->executeSingleScheduledTransfer($row, 'symfony');
                ++$successCount;
                $io->writeln(sprintf('OK virement_programme #%d', $scheduledId));
            } catch (\Throwable $e) {
                ++$failedCount;
                $this->executionService->handleExecutionFailure($row, $e, 'symfony');
                $io->error(sprintf('FAILED virement_programme #%d: %s', $scheduledId, $e->getMessage()));
            }
        }

        $io->success(sprintf('Scheduled transfers done with Symfony engine. success=%d failed=%d', $successCount, $failedCount));

        return Command::SUCCESS;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchDueRows(): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, user_id, carte_source_id, carte_dest_id, montant, devise, frequence, prochaine_execution
             FROM virement_programme
             WHERE actif = 1
               AND statut IN ("PENDING", "ACTIVE", "FAILED")
               AND prochaine_execution IS NOT NULL
             ORDER BY prochaine_execution ASC'
        );

        $now = new \DateTimeImmutable();

        return array_values(array_filter($rows, static function (array $row) use ($now): bool {
            $scheduledAtRaw = $row['prochaine_execution'] ?? null;
            if (!is_string($scheduledAtRaw) || trim($scheduledAtRaw) === '') {
                return false;
            }

            try {
                $scheduledAt = new \DateTimeImmutable($scheduledAtRaw);
            } catch (\Throwable) {
                return false;
            }

            return $scheduledAt <= $now;
        }));
    }
}
