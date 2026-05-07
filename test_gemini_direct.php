<?php
/**
 * Test direct de la clé API Gemini avec clé en dur
 */

$apiKey = "YOUR_GEMINI_API_KEY";

echo "🔍 Test de la clé API Gemini (direct)\n";
echo "Clé: " . substr($apiKey, 0, 15) . "...\n\n";

// Test 1: ListModels pour vérifier la clé
echo "1️⃣ Récupération de la liste des modèles...\n";
$url = "https://generativelanguage.googleapis.com/v1/models?key=" . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ Erreur cURL: " . $curlError . "\n";
    exit(1);
}

echo "HTTP Code: " . $httpCode . "\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Connexion réussie!\n\n";
} else {
    echo "❌ Erreur HTTP " . $httpCode . "\n";
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        echo "Erreur API: " . $data['error']['message'] . "\n";
        if (isset($data['error']['details'])) {
            foreach ($data['error']['details'] as $detail) {
                echo "  - " . $detail['reason'] . ": " . $detail['metadata']['quota_metric'] . "\n";
            }
        }
    }
    exit(1);
}

// Test 2: Appel simple à gemini-2.0-flash
echo "2️⃣ Test d'appel à gemini-2.0-flash:generateContent...\n";
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . urlencode($apiKey);

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Dis "OK"']
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ Erreur cURL: " . $curlError . "\n";
    exit(1);
}

echo "HTTP Code: " . $httpCode . "\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $data['candidates'][0]['content']['parts'][0]['text'];
        echo "✅ SUCCÈS! Réponse reçue:\n";
        echo "   \"" . $text . "\"\n\n";
        echo "✅ L'API Gemini fonctionne correctement!\n";
        exit(0);
    }
} else {
    echo "❌ Erreur HTTP " . $httpCode . "\n";
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        echo "Erreur API: " . $data['error']['message'] . "\n";
        if (isset($data['error']['details'])) {
            foreach ($data['error']['details'] as $detail) {
                echo "  - " . $detail['reason'] . "\n";
            }
        }
    } else {
        echo "Réponse: " . substr($response, 0, 300) . "\n";
    }
    exit(1);
}
