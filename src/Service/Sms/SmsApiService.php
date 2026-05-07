<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class SmsApiService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiKey,
        private string $senderName
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.smsapi.com/sms.do', [
                'body' => [
                    'to' => $phoneNumber,
                    'from' => $this->senderName,
                    'message' => $message,
                    'format' => 'json',
                ],
            ]);

            $data = $response->toArray();
            
            if (isset($data['status']) && $data['status'] === 'OK') {
                $this->logger->info('SMS sent successfully via SMSAPI', [
                    'phone' => $phoneNumber,
                    'message_id' => $data['list'][0]['id'] ?? null,
                ]);
                return true;
            }

            $this->logger->error('Failed to send SMS via SMSAPI', [
                'response' => $data,
                'phone' => $phoneNumber,
            ]);
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('SMSAPI sending error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        }
    }
}
