<?php

namespace App\Service;

use Twilio\Rest\Client;

class TwilioService
{
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;

    public function __construct(string $accountSid, string $authToken, string $fromNumber)
    {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->fromNumber = $fromNumber;
    }

    public function sendSms(string $toNumber, string $message): void
    {
        try {
            // Pour le développement local, on peut désactiver la vérification SSL
            // ou fournir un CurlClient personnalisé.
            $curlOptions = [
                \CURLOPT_SSL_VERIFYPEER => false,
                \CURLOPT_SSL_VERIFYHOST => false,
            ];
            $httpClient = new \Twilio\Http\CurlClient($curlOptions);
            
            $client = new Client($this->accountSid, $this->authToken, null, null, $httpClient);
            $client->messages->create(
                $toNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => $message
                ]
            );
        } catch (\Exception $e) {
            error_log('Twilio Error: ' . $e->getMessage());
            throw $e; // Re-throw to see the error in Symfony
        }
    }
}