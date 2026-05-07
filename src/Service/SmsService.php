<?php

namespace App\Service;

use App\Service\Sms\BrevoSmsService;
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
    private ?string $messagingServiceSid;
    private LoggerInterface $logger;
    private ?BrevoSmsService $brevoSms;
    /** Dernière combinaison d'indices après un échec complet (pour affichage ou API). */
    private ?string $lastFailureHintFr = null;
    private ?string $lastTwilioFailureHintFr = null;

    public function __construct(
        HttpClientInterface $httpClient,
        string $accountSid,
        string $authToken,
        string $fromNumber,
        ?string $messagingServiceSid = null,
        LoggerInterface $logger,
        ?BrevoSmsService $brevoSms = null,
    ) {
        $this->httpClient = $httpClient;
        $this->accountSid = trim($accountSid);
        $this->authToken = trim($authToken);
        $this->fromNumber = trim($fromNumber);
        $this->messagingServiceSid = $messagingServiceSid ? trim($messagingServiceSid) : null;
        $this->logger = $logger;
        $this->brevoSms = $brevoSms;
    }

    public function getLastFailureHintFr(): ?string
    {
        return $this->lastFailureHintFr;
    }

    /**
     * Envoi SMS via Twilio uniquement (utile quand on veut ignorer Brevo).
     */
    public function sendSmsTwilioOnly(string $to, string $message): bool
    {
        $this->lastFailureHintFr = null;
        $this->lastTwilioFailureHintFr = null;

        $to = $this->normalizeInternationalPhone($to);
        if ($to === '') {
            $this->lastFailureHintFr = 'Numéro de téléphone vide ou non reconnu (attendu : mobile tunisien 8 chiffres ou international +...).';
            $this->logger->error('SMS ignoré : numéro vide après normalisation (Twilio only).');

            return false;
        }

        $twilioConfigured = $this->accountSid !== '' && $this->authToken !== '' && $this->fromNumber !== '';
        if (!$twilioConfigured) {
            $this->lastFailureHintFr = 'Twilio non configuré : vérifiez TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER.';
            $this->logger->error('SMS Twilio impossible : configuration manquante.');

            return false;
        }

        if ($this->sendViaTwilio($to, $message)) {
            return true;
        }

        $this->lastFailureHintFr = $this->lastTwilioFailureHintFr
            ?? 'Twilio n\'a pas pu envoyer. Consultez var/log/dev.log pour le détail.';

        return false;
    }

    /**
     * Envoie un SMS à un numéro spécifique.
     * Le numéro 'to' doit être au format international (ex: +216...).
     */
    public function sendSms(string $to, string $message): bool
    {
        $this->lastFailureHintFr = null;
        $this->lastTwilioFailureHintFr = null;

        $to = $this->normalizeInternationalPhone($to);
        if ($to === '') {
            $this->lastFailureHintFr = 'Numéro de téléphone vide ou non reconnu (attendu : mobile tunisien 8 chiffres ou international +...).';
            $this->logger->error('SMS ignoré : numéro vide après normalisation.');

            return false;
        }

        $twilioConfigured = $this->accountSid !== '' && $this->authToken !== '' && $this->fromNumber !== '';
        $brevoLive = $this->brevoSms instanceof BrevoSmsService && $this->brevoSms->isConfiguredForProduction();

        // Brevo en premier (souvent fiable hors US) ; Twilio Trial échoue souvent vers +216 sans numéros vérifiés.
        if ($brevoLive && $this->brevoSms->sendSms($to, $message)) {
            return true;
        }

        $brevoHint = ($brevoLive && $this->brevoSms instanceof BrevoSmsService) ? $this->brevoSms->getLastFailureHintFr() : null;

        if ($brevoLive) {
            $this->logger->notice('SMS Brevo en échec, tentative Twilio.', ['dest' => $to]);
        }

        if ($twilioConfigured && $this->sendViaTwilio($to, $message)) {
            return true;
        }

        $twilioHint = $this->lastTwilioFailureHintFr;
        if ($twilioHint === null && $twilioConfigured) {
            $twilioHint = 'Twilio n\'a pas pu envoyer. En Trial, vérifiez le numéro dans « Verified Caller IDs ».';
        }

        $parts = [];
        if (($brevoHint ?? '') !== '') {
            $parts[] = $brevoHint;
        }
        if ($twilioConfigured && ($twilioHint ?? '') !== '') {
            $parts[] = $twilioHint;
        }

        if ($parts !== []) {
            // Brevo sans crédits : seul le correctif Brevo compte ; ne pas encombrer avec l’échec Twilio suivant.
            if (($brevoHint ?? '') !== '' && str_contains($brevoHint, 'crédits SMS insuffisants')) {
                $this->lastFailureHintFr = $brevoHint;
            } else {
                $this->lastFailureHintFr = implode(' ', $parts);
            }
        } elseif (!$brevoLive && !$twilioConfigured) {
            $this->lastFailureHintFr = 'Aucune passerelle SMS utilisée en production : désactivez SMS_TEST_MODE ou configurez Twilio.';
        } else {
            $this->lastFailureHintFr = 'Voir var/log/*.log (Brevo puis Twilio).';
        }

        $this->logger->error('SMS impossible : chaîne complète échouée.', [
            'user_hint_fr' => $this->lastFailureHintFr,
            'providers_detail' => implode(' | ', $parts),
        ]);

        return false;
    }

    private function sendViaTwilio(string $to, string $message): bool
    {
        $this->lastTwilioFailureHintFr = null;

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

            $body = [
                'To' => $to,
                'Body' => $message,
            ];

            // Si on a un Messaging Service SID (MG...), on l’utilise comme dans ton test.
            // Sinon, on fallback sur From (numéro Twilio).
            if ($this->messagingServiceSid !== null && $this->messagingServiceSid !== '') {
                $body['MessagingServiceSid'] = $this->messagingServiceSid;
            } else {
                $body['From'] = $this->fromNumber;
            }

            // Encoder le body en form-urlencoded explicitement
            $encodedBody = http_build_query($body);

            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $encodedBody,
                'timeout' => 25,
            ]);

            $statusCode = $response->getStatusCode();
            $raw = $response->getContent(false);

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info("SMS envoyé avec succès à $to (Twilio)", ['status' => $statusCode]);

                return true;
            }

            $decoded = json_decode($raw, true);
            $twilioMsg = \is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
            if ($twilioMsg === '' && \is_array($decoded) && isset($decoded['more_info'])) {
                $twilioMsg = (string) $decoded['more_info'];
            }
            if (stripos($twilioMsg, 'unverified') !== false || stripos($twilioMsg, 'Trial') !== false) {
                $this->lastTwilioFailureHintFr = 'Twilio (Trial) : le destinataire doit être vérifié (Verified Caller IDs) ou passer un compte payant.';
            } elseif ($twilioMsg !== '') {
                $trim = strlen($twilioMsg) > 140 ? substr($twilioMsg, 0, 137) . '...' : $twilioMsg;
                $this->lastTwilioFailureHintFr = 'Twilio : ' . $trim;
            }

            $this->logger->error("Échec Twilio SMS vers $to", [
                'http' => $statusCode,
                'twilio_message' => $twilioMsg !== '' ? $twilioMsg : substr($raw, 0, 400),
                'hint' => 'Compte Trial Twilio : le destinataire doit être dans "Verified Caller IDs". Géographies : autoriser le pays dans Twilio Messaging.',
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->lastTwilioFailureHintFr = 'Twilio : erreur réseau ou timeout.';
            $this->logger->error('Exception Twilio SMS : ' . $e->getMessage(), ['dest' => $to]);

            return false;
        }
    }

    /**
     * E.164 minimal : espaces retirés, 00 international, Tunisie locale 8 chiffres -> +216.
     */
    private function normalizeInternationalPhone(string $raw): string
    {
        $t = preg_replace('/[\s\-\.\(\)]/', '', trim($raw)) ?? '';
        if ($t === '') {
            return '';
        }

        if (str_starts_with($t, '+')) {
            return $t;
        }

        if (str_starts_with($t, '00')) {
            return '+' . substr($t, 2);
        }

        if (str_starts_with($t, '216') && strlen($t) >= 11) {
            return '+' . $t;
        }

        if (preg_match('/^[2-9]\d{7}$/', $t)) {
            return '+216' . $t;
        }

        // Tunisie saisie locale avec 0 initial (ex: 098765432)
        if (preg_match('/^0([2-9]\d{7})$/', $t, $m)) {
            return '+216' . $m[1];
        }

        if (preg_match('/^\d{10,15}$/', $t)) {
            return '+' . $t;
        }

        return '';
    }
}