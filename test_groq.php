<?php
/**
 * Test de la clé API Groq
 */

$apiKey = "YOUR_GROQ_API_KEY";

echo "🔍 Test de la clé API Groq\n";
echo "Clé: " . substr($apiKey, 0, 15) . "...\n\n";

// Test 1: Récupérer la liste des modèles
echo "1️⃣ Récupération de la liste des modèles Groq...\n";
$url = "https://api.groq.com/openai/v1/models";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);

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
    echo "✅ Modèles disponibles:\n";
    if (isset($data['data'])) {
        foreach (array_slice($data['data'], 0, 5) as $model) {
            echo "   • " . $model['id'] . "\n";
        }
    }
    echo "\n";
} else {
    echo "❌ Erreur HTTP " . $httpCode . "\n";
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        echo "Erreur: " . $data['error']['message'] . "\n";
    }
    echo "Réponse brute:\n" . substr($response, 0, 500) . "\n\n";
    exit(1);
}

// Test 2: Appel simple à mixtral-8x7b
echo "2️⃣ Test d'appel à mixtral-8x7b-32768:generateContent...\n";
$url = "https://api.groq.com/openai/v1/chat/completions";

$payload = [
    'model' => 'llama-3.1-8b-instant',
    'messages' => [
        [
            'role' => 'user',
            'content' => 'Dis "OK" en une seule ligne.'
        ]
    ],
    'temperature' => 0.7,
    'max_tokens' => 50
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
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
    if (isset($data['choices'][0]['message']['content'])) {
        $text = $data['choices'][0]['message']['content'];
        echo "✅ SUCCÈS! Réponse reçue:\n";
        echo "   \"" . trim($text) . "\"\n\n";
        echo "✅ L'API Groq fonctionne parfaitement!\n";
        exit(0);
    } else {
        echo "⚠️ Format de réponse inattendu\n";
        print_r($data);
    }
} else {
    echo "❌ Erreur HTTP " . $httpCode . "\n";
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        echo "Erreur: " . $data['error']['message'] . "\n";
    } else {
        echo "Réponse brute:\n" . substr($response, 0, 500) . "\n";
    }
    exit(1);
}
