<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class TwilioSmsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $accountSid,
        private string $authToken,
        private string $fromNumber
    ) {
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
            
            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => [
                    'From' => $this->fromNumber,
                    'To' => $phoneNumber,
                    'Body' => $message,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 201) {
                $this->logger->info('SMS sent successfully via Twilio', [
                    'phone' => $phoneNumber,
                    'from' => $this->fromNumber,
                ]);
                return true;
            }

            $this->logger->error('Failed to send SMS via Twilio', [
                'status_code' => $statusCode,
                'response' => $response->getContent(false),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Twilio SMS sending error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
            ]);
            
            return false;
        }
    }
}
