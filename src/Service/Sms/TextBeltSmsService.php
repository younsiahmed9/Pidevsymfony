<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class TextBeltSmsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiKey = 'textbelt' // Clé gratuite
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            $response = $this->httpClient->request('POST', 'https://textbelt.com/text', [
                'body' => [
                    'phone' => $phoneNumber,
                    'message' => $message,
                    'key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();
            
            if ($data['success'] ?? false) {
                $this->logger->info('SMS sent successfully via TextBelt', [
                    'phone' => $phoneNumber,
                    'message_id' => $data['textId'] ?? null,
                ]);
                return true;
            }

            $this->logger->error('Failed to send SMS via TextBelt', [
                'response' => $data,
                'phone' => $phoneNumber,
            ]);
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('TextBelt SMS sending error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        }
    }
}
