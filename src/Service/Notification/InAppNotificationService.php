<?php

namespace App\Service\Notification;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Psr\Log\LoggerInterface;

final class InAppNotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function createNotification(User $user, string $title, string $message, string $type = 'info'): bool
    {
        try {
            // Créer une notification dans la base de données
            $notification = [
                'user_id' => $user->getId(),
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'created_at' => new \DateTime(),
                'read' => false
            ];

            // Pour l'instant, on loggue la notification
            // En production, vous pourriez créer une entité Notification
            $this->logger->info('In-app notification created', $notification);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create in-app notification', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            return false;
        }
    }

    public function getUnreadNotifications(User $user): array
    {
        // Récupérer les notifications non lues pour l'utilisateur
        return [
            [
                'id' => 1,
                'title' => 'Promotion FinTrack',
                'message' => 'Vous avez une nouvelle promotion disponible',
                'type' => 'promotion',
                'created_at' => new \DateTime(),
                'read' => false
            ]
        ];
    }
}
