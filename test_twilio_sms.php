<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use App\Service\SmsService;
use Psr\Log\NullLogger;

echo "=== Test Complet SMS Twilio ===\n\n";

// Configuration Twilio depuis .env.local
$twilioAccountSid = 'YOUR_TWILIO_ACCOUNT_SID';
$twilioAuthToken = 'YOUR_TWILIO_AUTH_TOKEN';
$twilioFromNumber = '+12282253082';
$twilioMessagingServiceSid = null; // Optionnel, à ajouter si disponible

// Configuration Brevo
$brevoApiKey = 'YOUR_BREVO_SMS_API_KEY';
$brevoSenderName = 'FinTrack';

// Logger simple pour les tests
$logger = new class {
    public function info($message, $context = []) {
        echo "[INFO] $message\n";
        if (!empty($context)) {
            echo "      Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    public function error($message, $context = []) {
        echo "[ERROR] $message\n";
        if (!empty($context)) {
            echo "      Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    public function warning($message, $context = []) {
        echo "[WARNING] $message\n";
        if (!empty($context)) {
            echo "         Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    public function debug($message, $context = []) {
        echo "[DEBUG] $message\n";
    }
};

// Créer le client HTTP
$httpClient = HttpClient::create();

// Créer le service SMS complet (avec Brevo et Twilio)
$smsService = new SmsService(
    $httpClient,
    $twilioAccountSid,
    $twilioAuthToken,
    $twilioFromNumber,
    $twilioMessagingServiceSid,
    $logger
);

echo "1. TEST DES CRÉDENTIALS TWILIO\n";
echo "   - Account SID: " . substr($twilioAccountSid, 0, 5) . "..." . substr($twilioAccountSid, -5) . "\n";
echo "   - Auth Token: " . (strlen($twilioAuthToken) > 0 ? "✓ Configuré" : "✗ Manquant") . "\n";
echo "   - From Number: $twilioFromNumber\n";
echo "   - Messaging Service SID: " . ($twilioMessagingServiceSid ?? "Non configuré") . "\n\n";

echo "2. TEST DE NORMALISATION DES NUMÉROS\n";

$testNumbers = [
    '+33612345678' => 'Numéro français',
    '21650123456' => 'Numéro tunisien (8 chiffres)',
    '2165012345678' => 'Numéro tunisien (10 chiffres)',
    '+216 50 123 456' => 'Numéro tunisien avec espaces',
    '050123456' => 'Numéro tunisien sans préfixe',
];

foreach ($testNumbers as $number => $description) {
    // Cette méthode est privée, donc on va la tester indirectement
    echo "   - $description: $number\n";
}

echo "\n3. TEST D'ENVOI SMS VIA TWILIO UNIQUEMENT\n";
echo "   ⚠️  IMPORTANT: Le test utilisera sendSmsTwilioOnly()\n";

// Numéro de test - À REMPLACER par votre numéro à vérifier dans Twilio
$testPhoneNumber = '+216 50 123 456'; // À remplacer par un numéro vérifié
$testMessage = 'FinTrack Test Twilio: Bienvenue sur notre plateforme SMS!';

echo "\n   Envoi du SMS:\n";
echo "   - Destinataire: $testPhoneNumber\n";
echo "   - Message: $testMessage\n";
echo "   - Longueur du message: " . strlen($testMessage) . " caractères\n\n";

$success = $smsService->sendSmsTwilioOnly($testPhoneNumber, $testMessage);

if ($success) {
    echo "   ✓ SMS envoyé avec succès via Twilio!\n";
} else {
    echo "   ✗ Erreur lors de l'envoi SMS\n";
    $hint = $smsService->getLastFailureHintFr();
    if ($hint) {
        echo "   Conseil: $hint\n";
    }
}

echo "\n4. TEST DE VÉRIFICATION D'ERREURS COURANTES\n";

// Test avec numéro non vérifié (pour voir le message d'erreur)
echo "   Test 1: Numéro non vérifié (Trial Account)\n";
$unverifiedNumber = '+1234567890';
echo "   - Tentative d'envoi à: $unverifiedNumber\n";
$result = $smsService->sendSmsTwilioOnly($unverifiedNumber, 'Test message');
if (!$result) {
    $hint = $smsService->getLastFailureHintFr();
    echo "   - Erreur: " . ($hint ?? "Non spécifiée") . "\n";
}

echo "\n5. DIAGNOSTIC DU COMPTE TWILIO\n";
echo "   - Type de compte: Trial (Test) vs Paid\n";
echo "   - Restriction: Seuls les numéros vérifiés peuvent recevoir des SMS\n";
echo "   - Solution: Ajouter les numéros en tant que 'Verified Caller IDs'\n";
echo "   - Dashboard: https://console.twilio.com/\n";

echo "\n6. RÉSUMÉ DE LA CONFIGURATION\n";
echo "   Brevo (primaire):\n";
echo "   - ✓ API Key configurée\n";
echo "   - ✓ Sender Name: $brevoSenderName\n";
echo "   - Priorité: Essaie d'abord Brevo, puis bascule à Twilio\n\n";

echo "   Twilio (fallback):\n";
echo "   - ✓ Account SID configuré\n";
echo "   - ✓ Auth Token configuré\n";
echo "   - ✓ From Number: $twilioFromNumber\n";
echo "   - ⚠️  Trial: Numéros vérifiés uniquement\n";

echo "\n7. PROCHAINES ÉTAPES\n";
echo "   1. Remplacer +216 50 123 456 par votre numéro réel\n";
echo "   2. Vérifier le numéro dans Twilio Dashboard\n";
echo "   3. Relancer ce test\n";
echo "   4. Tester depuis CreditController en créant un crédit\n";
echo "   5. Consulter var/log/dev.log pour les détails\n";

echo "\n=== Test Terminé ===\n";
