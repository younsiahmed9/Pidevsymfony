<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service pour envoyer des SMS via l'API Twilio.
 */
class SmsService
{
    private HttpClientInterface $httpClient;
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        string $accountSid,
        string $authToken,
        string $fromNumber,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->fromNumber = $fromNumber;
        $this->logger = $logger;
    }

    /**
     * Envoie un SMS à un numéro spécifique.
     * Le numéro 'to' doit être au format international (ex: +216...).
     */
    public function sendSms(string $to, string $message): bool
    {
        if (strpos($to, '+') !== 0) {
            // Tentative de formatage basique si le '+' manque
            // Pour la Tunisie par exemple, si ça commence par 2, 5, 9...
            if (strlen($to) === 8) {
                $to = '+216' . $to;
            }
        }

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => [
                    'From' => $this->fromNumber,
                    'To' => $to,
                    'Body' => $message,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info("SMS envoyé avec succès à $to", ['status' => $statusCode]);
                return true;
            }

            $errorData = $response->toArray(false);
            $this->logger->error("Échec de l'envoi du SMS à $to", [
                'status' => $statusCode,
                'error' => $errorData['message'] ?? 'Erreur inconnue',
            ]);

            return false;
        } catch (\Exception $e) {
            $this->logger->error("Exception lors de l'envoi du SMS à $to : " . $e->getMessage());
            return false;
        }
    }
}
