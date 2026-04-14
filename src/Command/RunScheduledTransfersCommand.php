<?php

namespace App\Command;

use App\Entity\CarteVirtuelle;
use App\Entity\Transaction;
use App\Service\Transfer\BrevoEmailService;
use App\Service\Transfer\CurrencyRateService;
use App\Service\Transfer\TransferFeeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:transfers:run-scheduled', description: 'Executes due scheduled transfers')]
final class RunScheduledTransfersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrencyRateService $currencyRateService,
        private readonly BrevoEmailService $brevoEmailService,
        private readonly TransferFeeService $transferFeeService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->ensureDatabaseConnection();

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, user_id, carte_source_id, carte_dest_id, montant, devise, frequence, prochaine_execution
             FROM virement_programme
             WHERE actif = 1
               AND statut IN ("PENDING", "ACTIVE", "FAILED")
               AND prochaine_execution IS NOT NULL
             ORDER BY prochaine_execution ASC'
        );

        $now = new \DateTimeImmutable();
        $rows = array_values(array_filter($rows, static function (array $row) use ($now): bool {
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

        if ($rows === []) {
            $io->success('No due scheduled transfers.');

            return Command::SUCCESS;
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $row) {
            $scheduledId = (int) $row['id'];
            $this->ensureDatabaseConnection();

            try {
                $this->executeSingleScheduledTransfer($row);
                ++$successCount;
                $io->writeln(sprintf('OK virement_programme #%d', $scheduledId));
            } catch (\Throwable $e) {
                ++$failedCount;
                $this->ensureDatabaseConnection();

                $errorMessage = mb_substr($e->getMessage(), 0, 2000);
                $isPermanentFailure = $this->isPermanentScheduledFailure($e->getMessage());

                $this->entityManager->getConnection()->update('virement_programme', [
                    'statut' => 'FAILED',
                    'actif' => $isPermanentFailure ? 0 : 1,
                    'attempts' => (int) $this->entityManager->getConnection()->fetchOne(
                        'SELECT attempts FROM virement_programme WHERE id = :id',
                        ['id' => $scheduledId]
                    ) + 1,
                    'error_message' => $errorMessage,
                    'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ], ['id' => $scheduledId]);

                $this->entityManager->getConnection()->insert('transfer_execution_log', [
                    'virement_programme_id' => $scheduledId,
                    'execution_type' => 'AUTO',
                    'status' => 'FAILED',
                    'scheduled_for' => $row['prochaine_execution'],
                    'executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'transaction_id' => null,
                    'amount' => $row['montant'],
                    'currency' => $row['devise'],
                    'fee_amount' => '0.00',
                    'error_code' => $isPermanentFailure ? 'EXEC_ERROR_PERMANENT' : 'EXEC_ERROR',
                    'error_message' => $errorMessage,
                ]);

                $io->error(sprintf('FAILED virement_programme #%d: %s', $scheduledId, $e->getMessage()));
            }
        }

        $io->success(sprintf('Scheduled transfers done. success=%d failed=%d', $successCount, $failedCount));

        return Command::SUCCESS;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function executeSingleScheduledTransfer(array $row): void
    {
        $this->ensureDatabaseConnection();

        $scheduledId = (int) $row['id'];
        $sourceCardId = (int) ($row['carte_source_id'] ?? 0);
        $destCardId = (int) ($row['carte_dest_id'] ?? 0);
        $amount = (float) $row['montant'];
        $transferCurrency = (string) $row['devise'];

        if ($sourceCardId <= 0 || $destCardId <= 0) {
            throw new \RuntimeException('Scheduled transfer must define source and destination cards.');
        }

        $sourceCard = $this->entityManager->getRepository(CarteVirtuelle::class)->find($sourceCardId);
        $destCard = $this->entityManager->getRepository(CarteVirtuelle::class)->find($destCardId);

        if (!$sourceCard instanceof CarteVirtuelle || !$destCard instanceof CarteVirtuelle) {
            throw new \RuntimeException('Source or destination card not found.');
        }

        if (!$sourceCard->isIsActive() || !$destCard->isIsActive()) {
            throw new \RuntimeException('Source or destination card is inactive.');
        }

        if ($sourceCard->getId() === $destCard->getId()) {
            throw new \RuntimeException('Source and destination cards must be different.');
        }

        $sourceDebit = $this->currencyRateService->convert($amount, $transferCurrency, (string) $sourceCard->getDevise());
        $destCredit = $this->currencyRateService->convert($amount, $transferCurrency, (string) $destCard->getDevise());
        $feeData = $this->transferFeeService->calculateFeeForSourceCard((int) $sourceCard->getId(), $sourceDebit);
        $feeAmount = (float) $feeData['feeAmount'];
        $totalSourceDebit = $sourceDebit + $feeAmount;

        $sourceBalance = (float) $sourceCard->getSolde();
        if ($sourceBalance < $totalSourceDebit) {
            throw new \RuntimeException(sprintf(
                'Insufficient source balance. Available: %.2f %s, required: %.2f %s (including %.2f fee).',
                $sourceBalance,
                (string) $sourceCard->getDevise(),
                $totalSourceDebit,
                (string) $sourceCard->getDevise(),
                $feeAmount
            ));
        }

        $sourceCard->setSolde(number_format($sourceBalance - $totalSourceDebit, 2, '.', ''));
        $destCard->setSolde(number_format((float) $destCard->getSolde() + $destCredit, 2, '.', ''));

        $transaction = new Transaction();
        $transaction->setType('VIREMENT_PROGRAMME');
        $transaction->setMontant(number_format($amount, 2, '.', ''));
        $transaction->setDevise($transferCurrency);
        $transaction->setDescription('Exécution automatique de virement programmé #' . $scheduledId);
        $transaction->setStatut('SUCCESS');
        $transaction->setCarteSource($sourceCard);
        $transaction->setCarteDest($destCard);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $nextExecution = $this->computeNextExecution((string) $row['frequence'], new \DateTimeImmutable((string) $row['prochaine_execution']));

        $updates = [
            'last_executed' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'statut' => 'ACTIVE',
            'error_message' => null,
        ];

        if ($nextExecution) {
            $updates['prochaine_execution'] = $nextExecution->format('Y-m-d H:i:s');
        } else {
            $updates['actif'] = 0;
            $updates['statut'] = 'COMPLETED';
        }

        $this->entityManager->getConnection()->update('virement_programme', $updates, ['id' => $scheduledId]);

        $this->entityManager->getConnection()->insert('transfer_execution_log', [
            'virement_programme_id' => $scheduledId,
            'execution_type' => 'AUTO',
            'status' => 'SUCCESS',
            'scheduled_for' => $row['prochaine_execution'],
            'executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'transaction_id' => $transaction->getId(),
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $transferCurrency,
            'fee_amount' => number_format($feeAmount, 2, '.', ''),
            'error_code' => null,
            'error_message' => null,
        ]);

        $this->entityManager->getConnection()->insert('transfer_fee_event', [
            'transaction_id' => $transaction->getId(),
            'virement_programme_id' => $scheduledId,
            'source_card_id' => (int) $sourceCard->getId(),
            'dest_card_id' => (int) $destCard->getId(),
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $transferCurrency,
            'base_fee_rate' => (float) $feeData['baseRate'],
            'applied_fee_rate' => (float) $feeData['appliedRate'],
            'fixed_fee' => '0.00',
            'fee_amount' => number_format($feeAmount, 2, '.', ''),
            'transfer_count_in_window' => (int) $feeData['previousTransfers'],
            'window_days' => 0,
            'rule_name' => '2pct_then_1pct_after_5',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->entityManager->getConnection()->insert('transfer_risk_log', [
            'transaction_id' => $transaction->getId(),
            'virement_programme_id' => $scheduledId,
            'transfer_kind' => 'PROGRAMME',
            'ip_address' => null,
            'country_code' => null,
            'country_name' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
            'risk_score' => 0,
            'decision' => 'REVIEW',
            'reason' => 'No client IP for scheduled execution',
            'provider' => 'SYSTEM',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Fetch user email for notification
        $userEmail = $this->entityManager->getConnection()->fetchOne(
            'SELECT email FROM users WHERE id = :uid',
            ['uid' => (int) $row['user_id']]
        ) ?: '';

        if ($userEmail === '') {
            $userEmail = 'noreply@fintrack.local';
        }

        try {
            $this->brevoEmailService->sendScheduledTransferConfirmation([
                'scheduled_id' => $scheduledId,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $transferCurrency,
                'source_card' => (string) $sourceCard->getNumeroCarte(),
                'dest_card' => (string) $destCard->getNumeroCarte(),
                'executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], $userEmail);

            $this->logger->info('Scheduled transfer confirmation email sent', [
                'scheduled_id' => $scheduledId,
                'recipient' => $userEmail,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Scheduled transfer confirmation email failed', [
                'scheduled_id' => $scheduledId,
                'recipient' => $userEmail,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }
    }

    private function ensureDatabaseConnection(): void
    {
        $connection = $this->entityManager->getConnection();

        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $connection->close();
            $connection->executeQuery('SELECT 1');
        }
    }

    private function isPermanentScheduledFailure(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, 'must define source and destination cards')
            || str_contains($normalized, 'card not found')
            || str_contains($normalized, 'card is inactive')
            || str_contains($normalized, 'must be different');
    }

    private function computeNextExecution(string $frequency, \DateTimeImmutable $current): ?\DateTimeImmutable
    {
        return match (strtoupper($frequency)) {
            'QUOTIDIEN' => $current->modify('+1 day'),
            'HEBDOMADAIRE' => $current->modify('+1 week'),
            'MENSUEL' => $current->modify('+1 month'),
            default => null,
        };
    }
}
