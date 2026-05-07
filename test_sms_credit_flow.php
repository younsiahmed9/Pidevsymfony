<?php
/**
 * Test complet du flux SMS pour création de crédit
 * Simule l'envoi d'un SMS comme si un crédit était créé
 */

require 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Symfony\Component\HttpClient\HttpClient;

echo "=== TEST COMPLET FLUX SMS CRÉDIT ===\n\n";

// Configuration Twilio
$accountSid = 'YOUR_TWILIO_ACCOUNT_SID';
$authToken = 'YOUR_TWILIO_AUTH_TOKEN';
$fromNumber = '+18126668106';
$phoneDestination = '+21658407447';

echo "📱 Paramètres Twilio:\n";
echo "  Account SID: " . substr($accountSid, 0, 10) . "...\n";
echo "  From: " . $fromNumber . "\n";
echo "  To: " . $phoneDestination . "\n";
echo "  Auth Token: " . substr($authToken, 0, 10) . "...\n\n";

// Simulation du message SMS comme dans CreditController
$creditAmount = '1000.00';
$smsMessage = sprintf(
    'FinTrack: votre demande de crédit de %s DT est enregistrée (en attente). Nous vous informerons après traitement.',
    $creditAmount
);

echo "💬 Message SMS à envoyer:\n";
echo "  \"" . $smsMessage . "\"\n\n";

// Préparation de la requête Twilio
$url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
$auth = base64_encode("{$accountSid}:{$authToken}");

$data = [
    'From' => $fromNumber,
    'To' => $phoneDestination,
    'Body' => $smsMessage,
];

$postData = http_build_query($data);

// Envoi via cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $auth,
    'Content-Type: application/x-www-form-urlencoded',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 25);

echo "🚀 Envoi du SMS...\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Traitement de la réponse
if ($curlError) {
    echo "❌ ERREUR cURL: " . $curlError . "\n";
    exit(1);
}

if ($httpCode == 201) {
    $responseData = json_decode($response, true);
    
    echo "✅ SMS ENVOYÉ AVEC SUCCÈS!\n\n";
    echo "📊 Détails de la réponse Twilio:\n";
    echo "  HTTP Code: " . $httpCode . " (Created)\n";
    echo "  Message SID: " . ($responseData['sid'] ?? 'N/A') . "\n";
    echo "  Status: " . ($responseData['status'] ?? 'N/A') . "\n";
    echo "  From: " . ($responseData['from'] ?? 'N/A') . "\n";
    echo "  To: " . ($responseData['to'] ?? 'N/A') . "\n";
    echo "  Price: " . ($responseData['price'] ?? 'N/A') . "\n";
    echo "  Date Sent: " . ($responseData['date_sent'] ?? 'N/A') . "\n";
    
    echo "\n✅ LE MESSAGE SMS SERAIT AFFICHÉ À L'UTILISATEUR:\n";
    echo "   \"✅ Votre demande de crédit a été soumise. SMS de confirmation envoyé à 7447\"\n";
    
} else if ($httpCode == 400 || $httpCode == 400 || $httpCode == 401) {
    $errorData = json_decode($response, true);
    echo "❌ ERREUR SMS - HTTP " . $httpCode . "\n\n";
    echo "Message d'erreur Twilio:\n";
    echo "  Code: " . ($errorData['code'] ?? 'N/A') . "\n";
    echo "  Message: " . ($errorData['message'] ?? 'N/A') . "\n";
    echo "  Info: " . ($errorData['more_info'] ?? 'N/A') . "\n";
    
    echo "\n❌ LE MESSAGE SMS SERAIT AFFICHÉ À L'UTILISATEUR:\n";
    echo "   \"❌ Demande enregistrée, mais le SMS n'a pas pu être envoyé. ";
    echo "Erreur: " . ($errorData['message'] ?? 'Erreur inconnue') . "\"\n";
} else {
    echo "⚠️ ERREUR HTTP " . $httpCode . "\n";
    echo "Réponse: " . substr($response, 0, 200) . "...\n";
}

echo "\n=== FIN DU TEST ===\n";
?>
