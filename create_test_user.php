<?php
require 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

try {
    // Connexion directe à la base de données
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'password' => '',
        'dbname' => 'fintrack',
    ]);

    // Vérifier si l'utilisateur existe déjà
    $existingUser = $connection->fetchAssociative(
        'SELECT id FROM users WHERE email = ?',
        ['test@fintrack.local']
    );

    if ($existingUser) {
        echo "[✓] User already exists with ID: " . $existingUser['id'] . "\n";
        exit(0);
    }

    // Créer l'utilisateur d'abord (car clients.user_id referenec users.id)
    $hashedPassword = password_hash('Test1234!', PASSWORD_BCRYPT);
    
    $connection->insert('users', [
        'email' => 'test@fintrack.local',
        'password_hash' => $hashedPassword,
        'full_name' => 'Test User',
        'role' => 'CLIENT',
        'is_active' => 1,
        'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
        'updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
        'message_moderation_strikes' => 0,
        'moderation_warning_count' => 0,
    ]);

    $userId = $connection->lastInsertId();
    echo "[✓] User created with ID: " . $userId . "\n";

    // Créer le client (lié à l'utilisateur)
    $connection->insert('clients', [
        'user_id' => $userId,
        'phone' => '+21658407447',
        'cin' => NULL,
    ]);

    echo "[✓] Client created for user\n";
    echo "[✓] Email: test@fintrack.local\n";
    echo "[✓] Password: Test1234!\n";
    echo "[✓] Phone: +21658407447\n\n";
    echo "[INFO] You can now login at http://localhost:8001/login\n";

} catch (Exception $e) {
    echo "[✗] Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
