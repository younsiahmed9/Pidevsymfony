#!/usr/bin/env php
<?php
/**
 * Symfony SMS Test - Uses actual SmsService
 * Tests SMS sending through the application container
 */

// Setup base paths
define('APP_ROOT', __DIR__);

// Load Symfony Bootstrap
require __DIR__.'/vendor/autoload.php';

// Silence the platform check warning
putenv('COMPOSER_MEMORY_LIMIT=-1');

// Import Symfony kernel
try {
    require_once __DIR__.'/src/Kernel.php';
    
    echo "🧪 SYMFONY SMS SERVICE TEST\n";
    echo "============================\n\n";
    
    // Boot kernel
    $kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', false);
    $kernel->boot();
    $container = $kernel->getContainer();
    
    echo "✓ Symfony kernel booted\n";
    echo "✓ Environment: " . $_ENV['APP_ENV'] . "\n";
    echo "✓ Debug: " . ($_ENV['APP_DEBUG'] ? 'ON' : 'OFF') . "\n\n";
    
    // Get SMS Service
    $smsService = $container->get('App\Service\SmsService');
    echo "✓ SmsService loaded from container\n\n";
    
    // Test SMS
    $phone = '+18777804236';  // Same test number as before
    $message = 'Test SMS from Symfony App - ' . date('Y-m-d H:i:s');
    
    echo "📤 SENDING SMS VIA SYMFONY SERVICE\n";
    echo "-----------------------------------\n";
    echo "To: $phone\n";
    echo "Message: $message\n";
    echo "Method: sendSmsTwilioOnly (Twilio-only, no fallback)\n\n";
    
    // Send SMS using Twilio-only method (like CreditController does)
    $success = $smsService->sendSmsTwilioOnly($phone, $message);
    
    if ($success) {
        echo "✅ SMS SENT SUCCESSFULLY!\n\n";
        echo "✓ Status: Accepted\n";
        echo "✓ Provider: Twilio\n";
        echo "✓ Delivery: Expected in 5-10 seconds\n\n";
    } else {
        echo "❌ SMS FAILED TO SEND\n\n";
        $hint = $smsService->getLastFailureHintFr();
        echo "Error hint (FR): " . ($hint ?? 'Unknown error') . "\n\n";
    }
    
    echo "🎉 TEST COMPLETE!\n";
    echo "=================\n";
    
    $kernel->shutdown();
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
?>
