<?php
/**
 * Test du SmsService avec Symfony
 */

// Bootstrap Symfony
require_once 'vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use App\Service\SmsService;
use Psr\Log\NullLogger;

// Créer l'HttpClient
$httpClient = HttpClient::create();

// Créer le logger (NullLogger pour test)
$logger = new NullLogger();

// Créer BrevoSmsService (peut être null, c'est juste un fallback)
$brevoSms = null;

// Paramètres Twilio
$accountSid = 'YOUR_TWILIO_ACCOUNT_SID';
$authToken = 'YOUR_TWILIO_AUTH_TOKEN';
$fromNumber = '+18126668106';
$messagingServiceSid = null;  // C'est ça le problème - null!

echo "=== TEST SMSSERVICE DE SYMFONY ===\n\n";

// Créer le SmsService
$smsService = new SmsService(
    $httpClient,
    $accountSid,
    $authToken,
    $fromNumber,
    $messagingServiceSid,
    $logger,
    $brevoSms
);

echo "📱 Paramètres:\n";
echo "  Account SID: " . substr($accountSid, 0, 10) . "...\n";
echo "  From: " . $fromNumber . "\n";
echo "  MessagingServiceSid: " . ($messagingServiceSid ?? 'NULL') . "\n\n";

// Tester l'envoi
$phone = '+21658407447';
$message = 'FinTrack: votre demande de crédit de 1000.00 DT est enregistrée (en attente). Nous vous informerons après traitement.';

echo "🚀 Envoi du SMS...\n\n";

$result = $smsService->sendSmsTwilioOnly($phone, $message);

if ($result) {
    echo "✅ SMS ENVOYÉ AVEC SUCCÈS!\n";
} else {
    echo "❌ SMS N'A PAS PU ÊTRE ENVOYÉ\n";
    echo "Erreur: " . ($smsService->getLastFailureHintFr() ?? 'Unknown error') . "\n";
}

echo "\n=== FIN DU TEST ===\n";
?>
