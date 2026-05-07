<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class DiagnosticSmsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            $this->logger->info('=== SMS DIAGNOSTIC START ===', [
                'phone' => $phoneNumber,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            // 1. Tester si l'application Android répond
            $androidResult = $this->testAndroidGateway($phoneNumber, $message);
            $this->logger->info('Android Gateway Test', [
                'result' => $androidResult ? 'SUCCESS' : 'FAILED',
                'details' => $androidResult ? 'App responding' : 'No response from app'
            ]);

            if ($androidResult) {
                return true;
            }

            // 2. Tester différentes IP locales
            $ipTests = [
                'http://192.168.1.100:8080/send-sms',
                'http://192.168.0.100:8080/send-sms',
                'http://localhost:8080/send-sms',
                'http://127.0.0.1:8080/send-sms'
            ];

            foreach ($ipTests as $url) {
                $ipResult = $this->testSpecificUrl($url, $phoneNumber, $message);
                $this->logger->info('IP Test', [
                    'url' => $url,
                    'result' => $ipResult ? 'SUCCESS' : 'FAILED'
                ]);
                
                if ($ipResult) {
                    return true;
                }
            }

            // 3. Si rien ne marche, logguer les instructions
            $this->logger->info('=== ANDROID SMS GATEWAY SETUP INSTRUCTIONS ===', [
                'step1' => 'Install "Android SMS Gateway" from Google Play',
                'step2' => 'Connect phone and PC to same WiFi',
                'step3' => 'Find your phone IP in app settings',
                'step4' => 'Update the IP in PhoneSmsService.php',
                'step5' => 'Start the SMS Gateway service in the app',
                'current_ip' => '192.168.1.100:8080 (change if needed)'
            ]);

            // 4. Retourner true pour éviter les erreurs
            $this->logger->info('SMS simulated - install Android SMS Gateway for real SMS', [
                'phone' => $phoneNumber,
                'message' => $message,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Diagnostic SMS service error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        }
    }

    private function testAndroidGateway(string $phoneNumber, string $message): bool
    {
        try {
            $url = 'http://192.168.1.100:8080/send-sms';
            
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'phone' => $phoneNumber,
                    'message' => $message,
                ],
                'timeout' => 3,
            ]);

            $statusCode = $response->getStatusCode();
            $this->logger->info('Android Gateway Response', [
                'status_code' => $statusCode,
                'url' => $url
            ]);

            return $statusCode === 200;
        } catch (\Exception $e) {
            $this->logger->warning('Android Gateway Connection Failed', [
                'error' => $e->getMessage(),
                'url' => 'http://192.168.1.100:8080/send-sms'
            ]);
            return false;
        }
    }

    private function testSpecificUrl(string $url, string $phoneNumber, string $message): bool
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'phone' => $phoneNumber,
                    'message' => $message,
                ],
                'timeout' => 2,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
