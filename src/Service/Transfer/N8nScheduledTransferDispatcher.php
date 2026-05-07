<?php

namespace App\Service\Transfer;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class N8nScheduledTransferDispatcher
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private ScheduledTransferExecutionService $executionService,
        private LoggerInterface $logger,
        private string $webhookUrl,
        private string $internalToken,
        private int $timeoutSeconds,
        private string $symfonyBaseUrl,
    ) {
    }

    /**
     * @param array<int,array<string,mixed>> $dueRows
     *
     * @return array{sent:int,failed:int,webhook_status:int|null}
     */
    public function dispatchDueTransfers(array $dueRows): array
    {
        if ($dueRows === []) {
            return ['sent' => 0, 'failed' => 0, 'webhook_status' => null];
        }

        if (trim($this->webhookUrl) === '') {
            throw new \RuntimeException('N8N_SCHEDULED_TRANSFER_WEBHOOK is empty.');
        }

        $payloadRows = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'carte_source_id' => (int) ($row['carte_source_id'] ?? 0),
                'carte_dest_id' => (int) ($row['carte_dest_id'] ?? 0),
                'montant' => (string) ($row['montant'] ?? '0.00'),
                'devise' => (string) ($row['devise'] ?? 'TND'),
                'frequence' => (string) ($row['frequence'] ?? 'UNE_FOIS'),
                'prochaine_execution' => (string) ($row['prochaine_execution'] ?? ''),
            ];
        }, $dueRows);

        $response = $this->httpClient->request('POST', $this->webhookUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Scheduled-Transfer-Token' => $this->internalToken,
            ],
            'json' => [
                'source' => 'symfony_command',
                'dispatched_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'symfony_base_url' => $this->symfonyBaseUrl,
                'execute_all_url' => rtrim($this->symfonyBaseUrl, '/') . '/internal/n8n/scheduled-transfers/execute-all',
                'due_transfers' => $payloadRows,
            ],
            'timeout' => max(1, $this->timeoutSeconds),
        ]);

        $status = $response->getStatusCode();
        $isSuccess = $status >= 200 && $status < 300;

        $sent = 0;
        $failed = 0;

        foreach ($dueRows as $row) {
            $logPayload = [
                'virement_programme_id' => (int) ($row['id'] ?? 0),
                'execution_type' => 'N8N_DISPATCH',
                'status' => $isSuccess ? 'DISPATCHED' : 'FAILED_DISPATCH',
                'scheduled_for' => (string) ($row['prochaine_execution'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s')),
                'executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'transaction_id' => null,
                'amount' => (string) ($row['montant'] ?? '0.00'),
                'currency' => (string) ($row['devise'] ?? 'TND'),
                'fee_amount' => '0.00',
                'error_code' => $isSuccess ? null : 'N8N_WEBHOOK_ERROR',
                'error_message' => $isSuccess ? null : ('Webhook status ' . $status),
                'payload' => json_encode(['webhook_status' => $status], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];

            $this->executionService->insertExecutionLog('n8n', $logPayload);

            if ($isSuccess) {
                ++$sent;
            } else {
                ++$failed;
            }
        }

        $this->logger->info('n8n scheduled transfer dispatch result', [
            'webhook_url' => $this->webhookUrl,
            'webhook_status' => $status,
            'sent' => $sent,
            'failed' => $failed,
        ]);

        return ['sent' => $sent, 'failed' => $failed, 'webhook_status' => $status];
    }
}
