<?php

namespace App\Service\Transfer;

use App\Entity\CarteVirtuelle;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ScheduledTransferExecutionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrencyRateService $currencyRateService,
        private BrevoEmailService $brevoEmailService,
        private TransferFeeService $transferFeeService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public function executeSingleScheduledTransfer(array $row, string $channel = 'symfony'): void
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
        $transaction->setDescription('Execution automatique de virement programme #' . $scheduledId);
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

        $this->insertExecutionLog(
            $channel,
            [
                'virement_programme_id' => $scheduledId,
                'execution_type' => $channel === 'n8n' ? 'AUTO_N8N' : 'AUTO',
                'status' => 'SUCCESS',
                'scheduled_for' => $row['prochaine_execution'],
                'executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'transaction_id' => $transaction->getId(),
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $transferCurrency,
                'fee_amount' => number_format($feeAmount, 2, '.', ''),
                'error_code' => null,
                'error_message' => null,
            ]
        );

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
            'transfer_kind' => $channel === 'n8n' ? 'PROGRAMME_N8N' : 'PROGRAMME',
            'ip_address' => null,
            'country_code' => null,
            'country_name' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
            'risk_score' => 0,
            'decision' => 'REVIEW',
            'reason' => 'No client IP for scheduled execution',
            'provider' => strtoupper($channel === 'n8n' ? 'n8n' : 'system'),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

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
                'channel' => $channel,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Scheduled transfer confirmation email failed', [
                'scheduled_id' => $scheduledId,
                'recipient' => $userEmail,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'channel' => $channel,
            ]);
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    public function handleExecutionFailure(array $row, \Throwable $e, string $channel = 'symfony'): void
    {
        $this->ensureDatabaseConnection();

        $scheduledId = (int) ($row['id'] ?? 0);
        if ($scheduledId <= 0) {
            return;
        }

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

        $this->insertExecutionLog(
            $channel,
            [
                'virement_programme_id' => $scheduledId,
                'execution_type' => $channel === 'n8n' ? 'AUTO_N8N' : 'AUTO',
                'status' => 'FAILED',
                'scheduled_for' => $row['prochaine_execution'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'transaction_id' => null,
                'amount' => $row['montant'] ?? '0.00',
                'currency' => $row['devise'] ?? 'TND',
                'fee_amount' => '0.00',
                'error_code' => $isPermanentFailure ? 'EXEC_ERROR_PERMANENT' : 'EXEC_ERROR',
                'error_message' => $errorMessage,
            ]
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function insertExecutionLog(string $channel, array $payload): void
    {
        $table = $channel === 'n8n' ? 'transfer_execution_log_n8n' : 'transfer_execution_log';

        $data = [
            'virement_programme_id' => (int) ($payload['virement_programme_id'] ?? 0),
            'execution_type' => (string) ($payload['execution_type'] ?? 'AUTO'),
            'status' => (string) ($payload['status'] ?? 'SUCCESS'),
            'scheduled_for' => (string) ($payload['scheduled_for'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s')),
            'executed_at' => (string) ($payload['executed_at'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s')),
            'transaction_id' => $payload['transaction_id'],
            'amount' => (string) ($payload['amount'] ?? '0.00'),
            'currency' => (string) ($payload['currency'] ?? 'TND'),
            'fee_amount' => (string) ($payload['fee_amount'] ?? '0.00'),
            'error_code' => $payload['error_code'],
            'error_message' => $payload['error_message'],
        ];

        if ($channel === 'n8n') {
            $data['payload'] = $payload['payload'] ?? null;
        }

        $this->entityManager->getConnection()->insert($table, $data);
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
