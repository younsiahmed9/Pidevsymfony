<?php

namespace App\Service\Transfer;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class BrevoEmailService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $brevoApiKey,
        private readonly string $senderEmail,
        private readonly string $senderName,
    ) {
    }

    public function sendScheduledTransferConfirmation(array $payload, string $recipientEmail): void
    {
        if ($this->brevoApiKey === '') {
            throw new \RuntimeException('Brevo API key is not configured.');
        }

        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid recipient email address.');
        }

        $subject = sprintf(
            'Confirmation du virement programme #%s',
            (string) ($payload['scheduled_id'] ?? '')
        );

        $htmlContent = sprintf(
            '<html><body><h2>Virement programmé exécuté</h2><p><strong>ID:</strong> %s</p><p><strong>Montant:</strong> %s %s</p><p><strong>Carte source:</strong> %s</p><p><strong>Carte destination:</strong> %s</p><p><strong>Date:</strong> %s</p></body></html>',
            (string) ($payload['scheduled_id'] ?? ''),
            (string) ($payload['amount'] ?? ''),
            (string) ($payload['currency'] ?? ''),
            (string) ($payload['source_card'] ?? ''),
            (string) ($payload['dest_card'] ?? ''),
            (string) ($payload['executed_at'] ?? '')
        );

        $response = $this->httpClient->request('POST', 'https://api.brevo.com/v3/smtp/email', [
            'headers' => [
                'api-key' => $this->brevoApiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
            'json' => [
                'sender' => [
                    'name' => $this->senderName,
                    'email' => $this->senderEmail,
                ],
                'to' => [
                    [
                        'email' => $recipientEmail,
                    ],
                ],
                'subject' => $subject,
                'htmlContent' => $htmlContent,
            ],
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException('Brevo email request failed with status ' . $response->getStatusCode());
        }
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function verifyConnection(): array
    {
        if ($this->brevoApiKey === '') {
            return [
                'ok' => false,
                'message' => 'Brevo API key is not configured.',
            ];
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.brevo.com/v3/account', [
                'headers' => [
                    'api-key' => $this->brevoApiKey,
                    'accept' => 'application/json',
                ],
                'timeout' => 8,
            ]);

            if ($response->getStatusCode() >= 300) {
                return [
                    'ok' => false,
                    'message' => 'Brevo request failed with status ' . $response->getStatusCode(),
                ];
            }

            return [
                'ok' => true,
                'message' => 'Brevo API reachable',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => mb_substr($e->getMessage(), 0, 120),
            ];
        }
    }

    public function sendNormalTransferConfirmation(array $payload, string $recipientEmail): void
    {
        if ($this->brevoApiKey === '') {
            throw new \RuntimeException('Brevo API key is not configured.');
        }

        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid recipient email address.');
        }

        $subject = sprintf(
            'Confirmation du transfert #%s',
            (string) ($payload['transaction_id'] ?? '')
        );

        $htmlContent = sprintf(
            '<html><body><h2>Transfert confirmé</h2><p><strong>ID transaction:</strong> %s</p><p><strong>Montant:</strong> %s %s</p><p><strong>Carte source:</strong> %s</p><p><strong>Carte destination:</strong> %s</p><p><strong>Date:</strong> %s</p></body></html>',
            (string) ($payload['transaction_id'] ?? ''),
            (string) ($payload['amount'] ?? ''),
            (string) ($payload['currency'] ?? ''),
            (string) ($payload['source_card'] ?? ''),
            (string) ($payload['dest_card'] ?? ''),
            (string) ($payload['executed_at'] ?? '')
        );

        $response = $this->httpClient->request('POST', 'https://api.brevo.com/v3/smtp/email', [
            'headers' => [
                'api-key' => $this->brevoApiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
            'json' => [
                'sender' => [
                    'name' => $this->senderName,
                    'email' => $this->senderEmail,
                ],
                'to' => [
                    [
                        'email' => $recipientEmail,
                    ],
                ],
                'subject' => $subject,
                'htmlContent' => $htmlContent,
            ],
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException('Brevo email request failed with status ' . $response->getStatusCode());
        }
    }

    public function sendProgrammedTransferCreatedConfirmation(array $payload, string $recipientEmail): void
    {
        if ($this->brevoApiKey === '') {
            throw new \RuntimeException('Brevo API key is not configured.');
        }

        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid recipient email address.');
        }

        $subject = sprintf(
            'Virement programmé créé #%s',
            (string) ($payload['scheduled_id'] ?? '')
        );

        $htmlContent = sprintf(
            '<html><body><h2>Virement programmé créé</h2><p><strong>ID:</strong> %s</p><p><strong>Montant:</strong> %s %s</p><p><strong>Carte source:</strong> %s</p><p><strong>Carte destination:</strong> %s</p><p><strong>Prochaine exécution:</strong> %s</p><p><strong>Fréquence:</strong> %s</p></body></html>',
            (string) ($payload['scheduled_id'] ?? ''),
            (string) ($payload['amount'] ?? ''),
            (string) ($payload['currency'] ?? ''),
            (string) ($payload['source_card'] ?? ''),
            (string) ($payload['dest_card'] ?? ''),
            (string) ($payload['next_execution'] ?? ''),
            (string) ($payload['frequency'] ?? '')
        );

        $response = $this->httpClient->request('POST', 'https://api.brevo.com/v3/smtp/email', [
            'headers' => [
                'api-key' => $this->brevoApiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
            'json' => [
                'sender' => [
                    'name' => $this->senderName,
                    'email' => $this->senderEmail,
                ],
                'to' => [
                    [
                        'email' => $recipientEmail,
                    ],
                ],
                'subject' => $subject,
                'htmlContent' => $htmlContent,
            ],
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException('Brevo email request failed with status ' . $response->getStatusCode());
        }
    }
}
