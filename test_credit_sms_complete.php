<?php
/**
 * Test complet: Création crédit + Envoi SMS + Messages affichés à l'utilisateur
 */

require 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

echo "=== TEST COMPLET: CRÉATION CRÉDIT + SMS ===\n\n";

// Connexion BD
$connection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'password' => '',
    'dbname' => 'fintrack',
]);

// Récupérer l'utilisateur test
$user = $connection->fetchAssociative(
    'SELECT u.id, u.email, u.full_name, c.phone FROM users u 
     LEFT JOIN clients c ON c.user_id = u.id 
     WHERE u.email = ?',
    ['test@fintrack.local']
);

if (!$user) {
    echo "❌ Utilisateur test non trouvé!\n";
    exit(1);
}

echo "✓ Utilisateur trouvé:\n";
echo "  ID: " . $user['id'] . "\n";
echo "  Email: " . $user['email'] . "\n";
echo "  Nom: " . $user['full_name'] . "\n";
echo "  Téléphone: " . ($user['phone'] ?? 'VIDE') . "\n\n";

if (!$user['phone']) {
    echo "❌ PROBLÈME: L'utilisateur n'a pas de téléphone!\n";
    echo "Le SMS ne sera pas envoyé dans l'application.\n";
    exit(1);
}

// Créer un crédit
$creditData = [
    'user_id' => $user['id'],
    'montant' => 1000.00,
    'taux_interet' => 5.50,
    'duree_mois' => 12,
    'mensualite' => 86.07,
    'status' => 'en_attente',
    'date_debut' => (new DateTime())->format('Y-m-d H:i:s'),
    'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
    'compte_id' => NULL,
];

$connection->insert('credits', $creditData);
$creditId = $connection->lastInsertId();

echo "✓ Crédit créé:\n";
echo "  ID: " . $creditId . "\n";
echo "  Montant: " . $creditData['montant'] . " DT\n";
echo "  Statut: " . $creditData['status'] . "\n\n";

// Simuler l'envoi SMS comme dans CreditController
echo "📱 Envoi du SMS...\n\n";

$phone = trim($user['phone']);
$creditAmount = $creditData['montant'];

$accountSid = 'YOUR_TWILIO_ACCOUNT_SID';
$authToken = 'YOUR_TWILIO_AUTH_TOKEN';
$fromNumber = '+18126668106';

$smsMessage = sprintf(
    'FinTrack: votre demande de crédit de %s DT est enregistrée (en attente). Nous vous informerons après traitement.',
    $creditAmount
);

echo "Message SMS:\n";
echo "  \"" . $smsMessage . "\"\n";
echo "  → Envoyé vers: " . $phone . "\n\n";

// Appel API Twilio
$url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
$auth = base64_encode("{$accountSid}:{$authToken}");

$data = [
    'From' => $fromNumber,
    'To' => $phone,
    'Body' => $smsMessage,
];

$postData = http_build_query($data);

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

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Résultats
echo "========== RÉSULTAT FINAL ==========\n\n";

if ($curlError) {
    echo "❌ ERREUR: SMS non envoyé\n";
    echo "Raison: " . $curlError . "\n\n";
    echo "Message affiché à l'utilisateur:\n";
    echo "   \"❌ Demande enregistrée, mais le SMS n'a pas pu être envoyé.\n";
    echo "    Vérifiez votre numéro et réessayez.\"\n";
    exit(1);
}

if ($httpCode == 201) {
    $responseData = json_decode($response, true);
    
    echo "✅ SMS ENVOYÉ AVEC SUCCÈS!\n\n";
    echo "Détails Twilio:\n";
    echo "  Message SID: " . ($responseData['sid'] ?? 'N/A') . "\n";
    echo "  Status: " . ($responseData['status'] ?? 'N/A') . "\n";
    echo "  De: " . ($responseData['from'] ?? 'N/A') . "\n";
    echo "  Vers: " . ($responseData['to'] ?? 'N/A') . "\n\n";
    
    echo "✅ MESSAGE AFFICHÉ À L'UTILISATEUR:\n";
    echo "   \"✅ Votre demande de crédit a été soumise.\n";
    echo "    SMS de confirmation envoyé à " . substr($phone, -4) . "\"\n";
    
} else if ($httpCode == 400) {
    $errorData = json_decode($response, true);
    
    echo "❌ ERREUR: SMS bloqué par Twilio\n";
    echo "Code erreur: " . ($errorData['code'] ?? 'N/A') . "\n";
    echo "Message: " . ($errorData['message'] ?? 'N/A') . "\n\n";
    
    echo "Message affiché à l'utilisateur:\n";
    echo "   \"❌ Demande enregistrée, mais le SMS n'a pas pu être envoyé.\n";
    echo "    Erreur: " . ($errorData['message'] ?? 'Erreur inconnue') . "\"\n";
    
} else {
    echo "⚠️ ERREUR HTTP " . $httpCode . "\n";
    echo "Réponse: " . substr($response, 0, 200) . "\n";
}

echo "\n=== FIN DU TEST ===\n";
?>
