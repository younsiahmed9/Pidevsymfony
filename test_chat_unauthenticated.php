<?php

// Test the chat API with proper authentication
echo "Testing chat API with authentication flow...\n\n";

$curlSession = curl_init();

// Step 1: Get the login page to extract CSRF token (if needed)
echo "Step 1: Checking server...\n";
curl_setopt($curlSession, CURLOPT_URL, 'http://127.0.0.1:8001/');
curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curlSession, CURLOPT_TIMEOUT, 10);
curl_setopt($curlSession, CURLOPT_COOKIEJAR, 'C:/tmp/cookies.txt');
curl_setopt($curlSession, CURLOPT_VERBOSE, false);

$response = curl_exec($curlSession);
if (curl_errno($curlSession)) {
    echo "✗ Cannot reach server: " . curl_error($curlSession) . "\n";
    curl_close($curlSession);
    exit(1);
}
echo "✓ Server is reachable\n\n";

// Step 2: Try calling the chat endpoint without auth (to see exact error)
echo "Step 2: Calling chat endpoint without session...\n";
curl_setopt($curlSession, CURLOPT_URL, 'http://127.0.0.1:8001/service/recommendations/chat');
curl_setopt($curlSession, CURLOPT_POST, 1);
curl_setopt($curlSession, CURLOPT_POSTFIELDS, json_encode(['message' => 'Hello']));
curl_setopt($curlSession, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($curlSession, CURLOPT_TIMEOUT, 15);

$response = curl_exec($curlSession);
$httpCode = curl_getinfo($curlSession, CURLINFO_HTTP_CODE);

if (curl_errno($curlSession)) {
    echo "✗ cURL Error: " . curl_error($curlSession) . "\n";
    curl_close($curlSession);
    exit(1);
}

echo "HTTP Code: $httpCode\n";

if ($httpCode === 401) {
    echo "Response (401): User not authenticated - this is expected\n";
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "Message: " . ($decoded['message'] ?? 'N/A') . "\n";
    }
} else if ($httpCode === 500) {
    echo "✗ HTTP 500 - Server error\n";
    echo "Response (first 500 chars):\n" . substr($response, 0, 500) . "\n";
} else if ($httpCode === 200) {
    echo "✓ HTTP 200 - Success!\n";
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "Message: " . ($decoded['data']['message'] ?? 'N/A') . "\n";
    }
} else {
    echo "Response (first 500 chars):\n" . substr($response, 0, 500) . "\n";
}

curl_close($curlSession);

echo "\nNote: The endpoint requires authentication. To fully test, you need to be logged in.\n";
echo "The server timeout issue might be related to missing session/auth.\n";
