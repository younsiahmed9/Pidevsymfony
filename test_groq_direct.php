<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load .env.local
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env.local');

$groqKey = $_ENV['GROQ_API_KEY'] ?? 'NOT_SET';
echo "GROQ_API_KEY from .env.local: " . (strlen($groqKey) > 10 ? substr($groqKey, 0, 20) . "..." : $groqKey) . "\n";

// Test Groq directly
echo "\n✓ Testing Groq API directly...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'llama-3.1-8b-instant',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Say hello.'],
    ],
    'max_tokens' => 100,
    'temperature' => 0.35,
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $groqKey,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 12);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "Response received (first 200 chars):\n";
    echo substr($response, 0, 200) . "\n";
    
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($decoded['choices'][0]['message']['content'])) {
            echo "\n✓ Valid response from Groq!\n";
            echo "Message: " . $decoded['choices'][0]['message']['content'] . "\n";
        } else if (isset($decoded['error'])) {
            echo "\n✗ Groq error: " . $decoded['error']['message'] . "\n";
        }
    } else {
        echo "Invalid JSON response\n";
    }
}
