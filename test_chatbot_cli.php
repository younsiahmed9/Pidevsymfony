<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Symfony kernel
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env.local');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', $_SERVER['APP_DEBUG'] ?? false);
$kernel->boot();

$container = $kernel->getContainer();

// Get a test user from the database
$em = $container->get('doctrine.orm.entity_manager');
$userRepository = $em->getRepository(\App\Entity\User::class);
$user = $userRepository->findOneBy([]);

if (!$user) {
    echo "✗ No user found in database\n";
    exit(1);
}

echo "✓ Found user: " . $user->getEmail() . " (ID: " . $user->getId() . ")\n\n";

// Get the chatbot service
$chatbotService = $container->get(\App\Service\Chatbot\RecommendationChatbotService::class);

echo "Testing chatbot with message: 'Recommande-moi des services'\n\n";
echo "---\n";

try {
    $result = $chatbotService->processMessage($user, 'Recommande-moi des services');
    
    echo "---\n\n";
    echo "✓ Success!\n";
    echo "Result type: " . ($result['type'] ?? 'unknown') . "\n";
    echo "Message length: " . strlen($result['message'] ?? '') . " chars\n";
    echo "\nMessage (first 300 chars):\n";
    echo substr($result['message'] ?? '', 0, 300) . "\n";
    
} catch (\Throwable $e) {
    echo "---\n\n";
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "Type: " . get_class($e) . "\n";
}
