<?php
/**
 * Test rapide SMS Twilio
 * Envoie un SMS de test à un numéro vérifié
 */

// Credentials Twilio
$accountSid = 'YOUR_TWILIO_ACCOUNT_SID';
$authToken = 'YOUR_TWILIO_AUTH_TOKEN';
$fromNumber = '+18126668106';
$messagingServiceSid = '';

// Numéro de test (interne, sûr)
$toNumber = '+21658407447';

// Message
$message = 'FinTrack: Test SMS Twilio - ' . date('Y-m-d H:i:s');

// URL Twilio API
$url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

// Préparation des données
$data = [
    'From' => $fromNumber,
    'To' => $toNumber,
    'Body' => $message,
];

// Encodage en URL
$postData = http_build_query($data);

// Authentification Basic
$auth = base64_encode("{$accountSid}:{$authToken}");

// Création de la requête
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

// Envoi
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Résultat
$result = json_decode($response, true);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test SMS Twilio</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .error { color: #721c24; background: #f8d7da; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        th { background: #f9f9f9; font-weight: bold; text-align: left; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test SMS Twilio</h1>
        
        <h2>📊 Résultats</h2>
        
        <?php if ($httpCode === 201): ?>
            <div class="success">
                ✅ <strong>SMS envoyé avec succès!</strong> (HTTP 201)
            </div>
            
            <table>
                <tr><th colspan="2">Réponse Twilio</th></tr>
                <tr><td><strong>Message SID</strong></td><td><code><?php echo $result['sid'] ?? 'N/A'; ?></code></td></tr>
                <tr><td><strong>Status</strong></td><td><?php echo $result['status'] ?? 'N/A'; ?></td></tr>
                <tr><td><strong>Date Sent</strong></td><td><?php echo $result['date_sent'] ?? 'N/A'; ?></td></tr>
                <tr><td><strong>From</strong></td><td><?php echo $result['from'] ?? 'N/A'; ?></td></tr>
                <tr><td><strong>To</strong></td><td><?php echo $result['to'] ?? 'N/A'; ?></td></tr>
                <tr><td><strong>Body</strong></td><td><?php echo htmlspecialchars($result['body'] ?? 'N/A'); ?></td></tr>
                <tr><td><strong>Price</strong></td><td><?php echo ($result['price'] ?? '0.0075') . ' USD'; ?></td></tr>
            </table>
            
        <?php else: ?>
            <div class="error">
                ❌ <strong>Erreur d'envoi!</strong> (HTTP <?php echo $httpCode; ?>)
            </div>
            
            <?php if ($curlError): ?>
                <div class="error">
                    <strong>Erreur cURL:</strong> <?php echo $curlError; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($result['error_message'])): ?>
                <div class="error">
                    <strong>Erreur Twilio:</strong> <?php echo htmlspecialchars($result['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <h3>Réponse brute:</h3>
            <pre><?php echo htmlspecialchars($response); ?></pre>
        <?php endif; ?>
        
        <h2>📝 Détails de l'envoi</h2>
        <table>
            <tr><th>Paramètre</th><th>Valeur</th></tr>
            <tr><td>From (numéro Twilio)</td><td><code><?php echo $fromNumber; ?></code></td></tr>
            <tr><td>To (numéro destinataire)</td><td><code><?php echo $toNumber; ?></code></td></tr>
            <tr><td>Account SID</td><td><code><?php echo substr($accountSid, 0, 10) . '...'; ?></code></td></tr>
            <tr><td>Message</td><td><?php echo htmlspecialchars($message); ?></td></tr>
            <tr><td>HTTP Code</td><td><strong><?php echo $httpCode; ?></strong></td></tr>
            <tr><td>Timestamp</td><td><?php echo date('Y-m-d H:i:s'); ?></td></tr>
        </table>
        
        <h2>🔗 Liens utiles</h2>
        <ul>
            <li><a href="https://console.twilio.com/us/login" target="_blank">Twilio Console - Vérifier les messages</a></li>
            <li><a href="http://localhost:8001" target="_blank">Retour au site FinTrack</a></li>
        </ul>
    </div>
</body>
</html>
