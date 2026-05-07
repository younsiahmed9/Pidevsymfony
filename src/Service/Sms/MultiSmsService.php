<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class MultiSmsService
{
    public function __construct(
        private BrevoSmsService $brevoService,
        private ?TwilioSmsService $twilioService = null,
        private ?ClickatellSmsService $clickatellService = null,
        private ?SmsApiService $smsApiService = null,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        $services = [
            'brevo' => $this->brevoService,
            'twilio' => $this->twilioService,
            'clickatell' => $this->clickatellService,
            'smsapi' => $this->smsApiService,
        ];

        // Essayer chaque service jusqu'à ce qu'un fonctionne
        foreach ($services as $serviceName => $service) {
            if ($service === null) {
                continue;
            }

            try {
                $result = $service->sendSms($phoneNumber, $message);
                if ($result) {
                    $this->logger?->info("SMS sent successfully via {$serviceName}", [
                        'phone' => $phoneNumber,
                        'service' => $serviceName,
                    ]);
                    return true;
                }
            } catch (\Exception $e) {
                $this->logger?->warning("Failed to send SMS via {$serviceName}", [
                    'error' => $e->getMessage(),
                    'phone' => $phoneNumber,
                ]);
                continue;
            }
        }

        $this->logger?->error('All SMS services failed', [
            'phone' => $phoneNumber,
        ]);

        return false;
    }

    public function sendSmsWithFallback(string $phoneNumber, string $message): array
    {
        $results = [];
        $services = [
            'brevo' => $this->brevoService,
            'twilio' => $this->twilioService,
            'clickatell' => $this->clickatellService,
            'smsapi' => $this->smsApiService,
        ];

        foreach ($services as $serviceName => $service) {
            if ($service === null) {
                $results[$serviceName] = ['status' => 'not_configured'];
                continue;
            }

            try {
                $success = $service->sendSms($phoneNumber, $message);
                $results[$serviceName] = [
                    'status' => $success ? 'success' : 'failed',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                if ($success) {
                    break; // Arrêter après le premier succès
                }
            } catch (\Exception $e) {
                $results[$serviceName] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
        }

        return $results;
    }
}
