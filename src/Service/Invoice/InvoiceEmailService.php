<?php

namespace App\Service\Invoice;

use App\Entity\MailDeliveryLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class InvoiceEmailService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private string $brevoApiKey,
        private string $senderEmail,
        private string $senderName,
    ) {
    }

    public function sendInvoicePdf(
        string $recipientEmail,
        string $subject,
        string $htmlContent,
        string $pdfContent,
        string $filename,
        array $payload,
        ?User $actorUser = null
    ): void {
        if ($this->brevoApiKey === '') {
            throw new \RuntimeException('Brevo API key is not configured.');
        }

        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid recipient email address.');
        }

        $senderEmail = trim($this->senderEmail);

        if ($senderEmail === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid BREVO_SENDER_EMAIL configuration.');
        }

        if ($pdfContent === '') {
            throw new \RuntimeException('Invoice PDF content is empty.');
        }

        $log = (new MailDeliveryLog())
            ->setChannel('EMAIL')
            ->setMailTemplate('INVOICE_PDF')
            ->setRecipientEmail($recipientEmail)
            ->setSubject($subject)
            ->setStatus('PENDING')
            ->setProvider('BREVO')
            ->setPayload($this->encodePayload($payload));

        if ($actorUser instanceof User) {
            $log->setUser($actorUser);
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.brevo.com/v3/smtp/email', [
                'headers' => [
                    'api-key' => $this->brevoApiKey,
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'sender' => [
                        'name' => $this->senderName,
                        'email' => $senderEmail,
                    ],
                    'to' => [
                        [
                            'email' => $recipientEmail,
                        ],
                    ],
                    'subject' => $subject,
                    'htmlContent' => $htmlContent,
                    'attachment' => [
                        [
                            'name' => $filename,
                            'content' => base64_encode($pdfContent),
                        ],
                    ],
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 300) {
                $rawError = trim($response->getContent(false));
                $errorSnippet = $rawError !== '' ? mb_substr($rawError, 0, 500) : 'No response body.';

                throw new \RuntimeException(
                    sprintf('Brevo email request failed with status %d. Response: %s', $statusCode, $errorSnippet)
                );
            }

            $log->setStatus('SENT');
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $log->setStatus('FAILED');
            $log->setErrorMessage(mb_substr($exception->getMessage(), 0, 1000));
            $this->entityManager->persist($log);
            $this->entityManager->flush();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodePayload(array $payload): ?string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}