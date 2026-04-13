<?php

$dotenv = __DIR__ . '/.env.local';
if (is_file($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

$key = $_ENV['BREVO_API_KEY'] ?? '';
$senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? '';
$senderName = $_ENV['BREVO_SENDER_NAME'] ?? 'FinTrack';
$recipient = $argv[1] ?? $senderEmail;

if ($key === '' || $senderEmail === '' || $recipient === '') {
    fwrite(STDERR, "Missing BREVO_API_KEY or BREVO_SENDER_EMAIL or recipient.\n");
    exit(1);
}

$ch = curl_init('https://api.brevo.com/v3/account');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['api-key: ' . $key, 'accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Status: " . $status . PHP_EOL;
echo "Response: " . $response . PHP_EOL;

echo "\n\n--- Testing Email Send ---\n";
$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'api-key: ' . $key,
    'accept: application/json',
    'content-type: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'sender' => [
        'name' => $senderName,
        'email' => $senderEmail,
    ],
    'to' => [[
        'email' => $recipient,
    ]],
    'subject' => 'Test Email FinTrack',
    'htmlContent' => '<html><body><h1>Brevo test</h1><p>If you see this, delivery works.</p></body></html>',
]));

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Email Send Status: " . $status . PHP_EOL;
echo "Email Response: " . $response . PHP_EOL;

