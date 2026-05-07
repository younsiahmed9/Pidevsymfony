<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class ClickatellSmsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiKey
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            $response = $this->httpClient->request('POST', 'https://platform.clickatell.com/messages', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $this->apiKey,
                ],
                'json' => [
                    'content' => $message,
                    'to' => [$phoneNumber],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 202) {
                $this->logger->info('SMS sent successfully via Clickatell', [
                    'phone' => $phoneNumber,
                ]);
                return true;
            }

            $this->logger->error('Failed to send SMS via Clickatell', [
                'status_code' => $statusCode,
                'response' => $response->getContent(false),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Clickatell SMS sending error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        }
    }
}
