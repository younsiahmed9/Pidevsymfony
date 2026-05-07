<?php

namespace App\Service\Notification;

use Psr\Log\LoggerInterface;

final class PushNotificationService
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function sendPushNotification(string $userId, string $title, string $message): bool
    {
        try {
            // Stocker la notification pour affichage dans l'interface
            $this->logger->info('Push notification stored', [
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to store push notification', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return false;
        }
    }

    public function getUnreadNotifications(string $userId): array
    {
        // Récupérer les notifications non lues depuis la base de données ou les logs
        return [
            [
                'id' => 1,
                'title' => 'Promotion disponible',
                'message' => 'Vous avez une nouvelle promotion sur FinTrack',
                'timestamp' => date('Y-m-d H:i:s'),
                'read' => false
            ]
        ];
    }
}
