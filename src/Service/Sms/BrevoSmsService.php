<?php

namespace App\Service\Sms;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class BrevoSmsService
{
    private ?string $lastFailureHintFr = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiKey,
        private string $sender,
        private bool $testMode = false
    ) {
    }

    /** Indication courte après un envoi réel qui a échoué ; null sinon. */
    public function getLastFailureHintFr(): ?string
    {
        return $this->lastFailureHintFr;
    }

    public function isConfiguredForProduction(): bool
    {
        return $this->apiKey !== '' && $this->sender !== '' && !$this->testMode;
    }

    public function sendSms(string $phoneNumber, string $message): bool
    {
        // Mode test : simuler l'envoi sans appeler l'API
        if ($this->testMode) {
            $this->lastFailureHintFr = null;
            $this->logger->info('SMS sent successfully (TEST MODE)', [
                'phone' => $phoneNumber,
                'sender' => $this->sender,
                'message' => $message,
                'test_mode' => true,
            ]);
            return true;
        }

        $this->lastFailureHintFr = null;

        try {
            $response = $this->httpClient->request('POST', 'https://api.brevo.com/v3/transactionalSMS/sms', [
                'timeout' => 20,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api-key' => $this->apiKey,
                ],
                'json' => [
                    'sender' => $this->sender,
                    'recipient' => $phoneNumber,
                    'content' => $message,
                    'type' => 'transactional',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('SMS Brevo OK', [
                    'phone' => $phoneNumber,
                    'sender' => $this->sender,
                    'status' => $statusCode,
                ]);

                return true;
            }

            $rawBody = $response->getContent(false);
            $this->lastFailureHintFr = $this->hintFromBrevoResponse($statusCode, $rawBody);

            $this->logger->error('Failed to send SMS', [
                'status_code' => $statusCode,
                'response' => $rawBody,
                'phone' => $phoneNumber,
                'hint_fr' => $this->lastFailureHintFr,
            ]);

            return false;
        } catch (\Exception $e) {
            $this->lastFailureHintFr = 'Erreur réseau ou timeout vers l\'API SMS Brevo.';
            $this->logger->error('SMS sending error', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber,
                'hint_fr' => $this->lastFailureHintFr,
            ]);

            return false;
        }
    }

    private function hintFromBrevoResponse(int $statusCode, string $rawBody): string
    {
        $decoded = json_decode($rawBody, true);
        $code = \is_array($decoded) ? (string) ($decoded['code'] ?? '') : '';
        $msg = \is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';

        if ($statusCode === 402 || $code === 'not_enough_credits') {
            return 'Brevo : crédits SMS insuffisants (402). Ajoutez des crédits dans Brevo (Facturation / options SMS).';
        }

        if ($statusCode === 401 || str_contains(strtolower($msg), 'unauthorized')) {
            return 'Brevo : clé API refusée (401). Vérifiez BREVO_SMS_API_KEY et les droits SMS.';
        }

        if ($statusCode === 403) {
            return 'Brevo : envoi refusé (403). Vérifiez que l\'option SMS est activée et l\'expéditeur agréé (BREVO_SMS_SENDER).';
        }

        if ($code === 'invalid_parameter') {
            if (stripos($msg, 'telephone') !== false || stripos($msg, 'phone') !== false || stripos($msg, 'number') !== false) {
                return 'Brevo : numéro international invalide. Indiquez le mobile au format +216XXXXXXXX.';
            }

            return 'Brevo : paramètre invalide. ' . ($msg !== '' ? $msg : "HTTP $statusCode");
        }

        if ($statusCode >= 400 && $msg !== '') {
            return 'Brevo : ' . $msg;
        }

        return sprintf('Brevo a renvoyé HTTP %d pour l\'envoi SMS.', $statusCode);
    }

    public function sendVerificationCode(string $phoneNumber, string $code): bool
    {
        $message = "Votre code de vérification FinTrack est : {$code}. Il expire dans 10 minutes.";
        
        return $this->sendSms($phoneNumber, $message);
    }

    public function sendAlert(string $phoneNumber, string $alertMessage): bool
    {
        $message = "Alerte FinTrack : {$alertMessage}";
        
        return $this->sendSms($phoneNumber, $message);
    }
}
