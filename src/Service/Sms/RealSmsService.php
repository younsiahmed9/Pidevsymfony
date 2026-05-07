<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class RealSmsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            // Essayer plusieurs services SMS réels et gratuits
            
            // 1. TextBelt (100 SMS gratuits/mois)
            $result1 = $this->sendViaTextBelt($phoneNumber, $message);
            if ($result1) return true;
            
            // 2. Fast2SMS (service gratuit)
            $result2 = $this->sendViaFast2Sms($phoneNumber, $message);
            if ($result2) return true;
            
            // 3. SMSAPI (mode test gratuit)
            $result3 = $this->sendViaSmsApi($phoneNumber, $message);
            if ($result3) return true;
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Real SMS service error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        }
    }
    
    private function sendViaTextBelt(string $phoneNumber, string $message): bool
    {
        try {
            $response = $this->httpClient->request('POST', 'https://textbelt.com/text', [
                'body' => [
                    'phone' => $phoneNumber,
                    'message' => $message,
                    'key' => 'textbelt'
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

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function sendViaFast2Sms(string $phoneNumber, string $message): bool
    {
        try {
            $response = $this->httpClient->request('POST', 'https://www.fast2sms.com/dev/bulkV2', [
                'headers' => [
                    'authorization' => 'demo-key', // Clé de démonstration
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
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function sendViaSmsApi(string $phoneNumber, string $message): bool
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.smsapi.com/sms.do', [
                'body' => [
                    'to' => $phoneNumber,
                    'from' => 'Test',
                    'message' => $message,
                    'format' => 'json',
                    'test' => '1', // Mode test gratuit
                ],
            ]);

            $data = $response->toArray();
            
            if (isset($data['status']) && $data['status'] === 'OK') {
                $this->logger->info('SMS sent successfully via SMSAPI (test mode)', [
                    'phone' => $phoneNumber,
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
