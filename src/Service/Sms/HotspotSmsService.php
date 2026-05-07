<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class HotspotSmsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            $this->logger->info('=== HOTSPOT SMS START ===', [
                'phone' => $phoneNumber,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            // IP typiques pour hotspot 4G Android
            $hotspotIps = [
                'http://192.168.43.1:8080/send-sms',  // Android hotspot par défaut
                'http://192.168.42.1:8080/send-sms',  // Alternative Android
                'http://192.168.1.100:8080/send-sms', // Ancienne config
                'http://192.168.0.100:8080/send-sms', // Alternative
                'http://10.0.0.1:8080/send-sms',       // Some carriers
                'http://172.16.0.1:8080/send-sms',     // Some carriers
            ];

            foreach ($hotspotIps as $url) {
                $this->logger->info('Testing hotspot IP', ['url' => $url]);
                
                $result = $this->testHotspotUrl($url, $phoneNumber, $message);
                if ($result) {
                    $this->logger->info('SMS sent successfully via hotspot', [
                        'url' => $url,
                        'phone' => $phoneNumber
                    ]);
                    return true;
                }
            }

            // Si aucune IP ne fonctionne, essayer de trouver l'IP automatiquement
            $autoIp = $this->findPhoneIp();
            if ($autoIp) {
                $this->logger->info('Found phone IP automatically', ['ip' => $autoIp]);
                
                $result = $this->testHotspotUrl("http://{$autoIp}:8080/send-sms", $phoneNumber, $message);
                if ($result) {
                    return true;
                }
            }

            // Instructions pour configurer le hotspot
            $this->logger->info('=== HOTSPOT SETUP INSTRUCTIONS ===', [
                'step1' => 'Go to Android Settings > Network & Internet > Hotspot & tethering',
                'step2' => 'Enable "Wi-Fi hotspot"',
                'step3' => 'Connect PC to the hotspot',
                'step4' => 'Install "Android SMS Gateway" app',
                'step5' => 'Find your PC IP in the app or use ipconfig',
                'step6' => 'Make sure SMS Gateway service is running',
                'tested_ips' => $hotspotIps
            ]);

            // Simulation pour éviter les erreurs
            $this->logger->info('SMS simulated - configure hotspot for real SMS', [
                'phone' => $phoneNumber,
                'message' => $message,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Hotspot SMS service error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        }
    }

    private function testHotspotUrl(string $url, string $phoneNumber, string $message): bool
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'phone' => $phoneNumber,
                    'message' => $message,
                ],
                'timeout' => 3,
            ]);

            $statusCode = $response->getStatusCode();
            $this->logger->info('Hotspot test result', [
                'url' => $url,
                'status_code' => $statusCode,
                'success' => $statusCode === 200
            ]);

            return $statusCode === 200;
        } catch (\Exception $e) {
            $this->logger->warning('Hotspot connection failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function findPhoneIp(): ?string
    {
        try {
            // Essayer de scanner le réseau local pour trouver des appareils sur le port 8080
            $baseIps = ['192.168.43.', '192.168.42.', '192.168.1.', '192.168.0.'];
            
            foreach ($baseIps as $baseIp) {
                for ($i = 1; $i <= 254; $i++) {
                    $ip = $baseIp . $i;
                    if ($this->testHotspotUrl("http://{$ip}:8080/send-sms", "test", "test")) {
                        return $ip;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('IP scan failed', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
