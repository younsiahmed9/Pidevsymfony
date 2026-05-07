<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class Fast2SmsService
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
            $response = $this->httpClient->request('POST', 'https://www.fast2sms.com/dev/bulkV2', [
                'headers' => [
                    'authorization' => $this->apiKey,
                ],
                'body' => [
                    'route' => 'v3',
                    'sender_id' => 'FTWSMS',
                    'message' => $message,
                    'language' => 'english',
                    'numbers' => $phoneNumber,
                ],
            ]);

            $data = $response->toArray();
            
            if (($data['return'] ?? false) && ($data['status_code'] ?? 0) === 200) {
                $this->logger->info('SMS sent successfully via Fast2SMS', [
                    'phone' => $phoneNumber,
                    'message_id' => $data['message'][0]['id'] ?? null,
                ]);
                return true;
            }

            $this->logger->error('Failed to send SMS via Fast2SMS', [
                'response' => $data,
                'phone' => $phoneNumber,
            ]);
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Fast2SMS sending error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        }
    }
}
