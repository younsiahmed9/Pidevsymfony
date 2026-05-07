<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use App\Service\Sms\BrevoSmsService;

// Configuration
$apiKey = 'YOUR_BREVO_SMS_API_KEY';
$sender = 'FinTrack';

// Création du client HTTP
$httpClient = HttpClient::create();

// Création d'un logger simple
$logger = new class {
    public function info($message, $context = []) {
        echo "[INFO] $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    public function error($message, $context = []) {
        echo "[ERROR] $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
};

// Création du service SMS
$smsService = new BrevoSmsService($httpClient, $logger, $apiKey, $sender);

// Test d'envoi de SMS
echo "Test d'envoi de SMS...\n";

// Remplacez ce numéro par votre numéro de téléphone réel pour le test
$phoneNumber = '+33612345678'; // Mettez votre numéro ici
$message = 'Test SMS depuis FinTrack - Service SMS fonctionnel!';

$success = $smsService->sendSms($phoneNumber, $message);

if ($success) {
    echo "SMS envoyé avec succès!\n";
} else {
    echo "Échec de l'envoi du SMS.\n";
}

echo "\nTest terminé.\n";
