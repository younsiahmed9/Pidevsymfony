<?php

namespace App\Service\Sms;

use Psr\Log\LoggerInterface;

final class LocalSmsGatewayService
{
    public function __construct(
        private LoggerInterface $logger,
        private string $gatewayScriptPath
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            // Option 1: Utiliser un script Python avec gateway gratuite
            $result = $this->sendViaPythonGateway($phoneNumber, $message);
            
            if ($result) {
                $this->logger->info('SMS sent successfully via local gateway', [
                    'phone' => $phoneNumber,
                    'method' => 'python_gateway',
                ]);
                return true;
            }

            // Option 2: Utiliser Android SMS Gateway (gratuit)
            $result = $this->sendViaAndroidGateway($phoneNumber, $message);
            
            if ($result) {
                $this->logger->info('SMS sent successfully via Android gateway', [
                    'phone' => $phoneNumber,
                    'method' => 'android_gateway',
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Local SMS gateway error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        }
    }

    private function sendViaPythonGateway(string $phoneNumber, string $message): bool
    {
        // Utiliser une gateway Python gratuite comme "python-sms-gateway"
        $command = sprintf(
            'python %s/send_sms.py %s %s',
            $this->gatewayScriptPath,
            escapeshellarg($phoneNumber),
            escapeshellarg($message)
        );

        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    }

    private function sendViaAndroidGateway(string $phoneNumber, string $message): bool
    {
        // Utiliser une app Android comme gateway
        // API REST gratuite : https://github.com/aleksandrjilc/Android-SMS-Gateway-Service
        try {
            $url = 'http://192.168.1.100:8080/send-sms'; // IP de votre téléphone
            $data = [
                'phone' => $phoneNumber,
                'message' => $message,
            ];

            $options = [
                'http' => [
                    'header'  => 'Content-type: application/json',
                    'method'  => 'POST',
                    'content' => json_encode($data),
                ],
            ];
            
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            
            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
