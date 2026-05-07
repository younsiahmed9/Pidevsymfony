<?php
/**
 * Direct Twilio SMS Test (No Composer Dependency)
 * Tests new Twilio account credentials
 */

echo "🧪 TWILIO SMS DIRECT TEST\n";
echo "========================\n\n";

// Load .env.local manually
$env_file = __DIR__ . '/.env.local';
if (!file_exists($env_file)) {
    die("❌ .env.local not found!\n");
}

$env_vars = [];
foreach (file($env_file) as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        [$key, $value] = explode('=', $line, 2);
        $env_vars[trim($key)] = trim($value, '\'"');
    }
}

// Extract Twilio credentials
$account_sid = $env_vars['TWILIO_ACCOUNT_SID'] ?? null;
$auth_token = $env_vars['TWILIO_AUTH_TOKEN'] ?? null;
$from_number = $env_vars['TWILIO_FROM_NUMBER'] ?? null;
$messaging_service_sid = $env_vars['TWILIO_MESSAGING_SERVICE_SID'] ?? null;

echo "1️⃣ CHECKING CREDENTIALS\n";
echo "------------------------\n";
echo "✓ Account SID: " . substr($account_sid, 0, 10) . "..." . substr($account_sid, -4) . "\n";
echo "✓ Auth Token: " . substr($auth_token, 0, 10) . "...\n";
echo "✓ From Number: $from_number\n";
echo "✓ Messaging Service SID: " . (substr($messaging_service_sid, 0, 10) . "...") . "\n\n";

if (!$account_sid || !$auth_token) {
    die("❌ Missing Twilio credentials!\n");
}

// Test 1: Validate credentials format
echo "2️⃣ VALIDATING CREDENTIAL FORMAT\n";
echo "--------------------------------\n";
if (!preg_match('/^AC[a-f0-9]{32}$/', $account_sid)) {
    die("❌ Invalid Account SID format (got: $account_sid)\n");
}
echo "✓ Account SID format valid\n";

if (strlen($auth_token) < 32) {
    die("❌ Invalid Auth Token format\n");
}
echo "✓ Auth Token format valid\n";

if (!preg_match('/^\+\d{10,15}$/', $from_number)) {
    die("❌ Invalid From Number format\n");
}
echo "✓ From Number format valid\n\n";

// Test 2: Test HTTP connectivity to Twilio
echo "3️⃣ TESTING TWILIO API CONNECTIVITY\n";
echo "------------------------------------\n";

$to_number = "+18777804236";  // Same as curl test
$message = "Test SMS from PHP - " . date('H:i:s');

// Use MessagingServiceSid if available (like the curl test did)
$post_data = [
    'To' => $to_number,
    'Body' => $message
];

if ($messaging_service_sid) {
    $post_data['MessagingServiceSid'] = $messaging_service_sid;
} else {
    $post_data['From'] = $from_number;
}

// Build query string
$query = http_build_query($post_data);

// Prepare cURL
$url = "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json";
$auth = base64_encode("$account_sid:$auth_token");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$account_sid:$auth_token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 25);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

echo "📤 Sending SMS...\n";
echo "   To: $to_number\n";
echo "   Message: " . substr($message, 0, 50) . "...\n";
echo "   Using: " . ($messaging_service_sid ? "MessagingServiceSid" : "From Number") . "\n\n";

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "❌ cURL Error: $curl_error\n";
    exit(1);
}

echo "HTTP Status: $http_code\n";

if ($http_code === 201) {
    echo "✅ SMS ACCEPTED BY TWILIO!\n\n";
    
    $response_data = json_decode($response, true);
    if ($response_data) {
        echo "4️⃣ RESPONSE DETAILS\n";
        echo "-------------------\n";
        echo "✓ Message SID: " . ($response_data['sid'] ?? 'N/A') . "\n";
        echo "✓ Status: " . ($response_data['status'] ?? 'N/A') . "\n";
        echo "✓ To: " . ($response_data['to'] ?? 'N/A') . "\n";
        echo "✓ Date Created: " . ($response_data['date_created'] ?? 'N/A') . "\n\n";
        
        echo "✅ SUCCESS! SMS sent successfully to Twilio!\n";
        echo "   SMS Status: " . $response_data['status'] . "\n";
        echo "   Message ID: " . $response_data['sid'] . "\n";
        echo "   Expected delivery: 5-10 seconds\n\n";
    }
} elseif ($http_code === 400 || $http_code === 401) {
    echo "❌ AUTHENTICATION ERROR\n";
    $error = json_decode($response, true);
    if ($error) {
        echo "Error: " . ($error['message'] ?? 'Unknown') . "\n";
        echo "Code: " . ($error['code'] ?? 'Unknown') . "\n";
    }
    exit(1);
} else {
    echo "❌ UNEXPECTED HTTP STATUS: $http_code\n";
    echo "Response: " . substr($response, 0, 200) . "\n";
    exit(1);
}

echo "🎉 TWILIO SMS TEST COMPLETE!\n";
echo "============================\n";
?>
