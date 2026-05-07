<?php

// Test the chat API endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8001/service/recommendations/chat');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => 'Salut']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_COOKIE, 'SYMFONY_SESSION=test');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "Response:\n";
    echo $response . "\n";
    
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "\n✓ Valid JSON response\n";
        echo "Success: " . ($decoded['success'] ? 'yes' : 'no') . "\n";
        if (isset($decoded['message'])) {
            echo "Message: " . $decoded['message'] . "\n";
        }
    } else {
        echo "Invalid JSON: " . json_last_error_msg() . "\n";
    }
}
