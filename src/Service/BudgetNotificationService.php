<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class BudgetNotificationService
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    /**
     * Vérifie si un budget a dépassé le seuil de 80% des dépenses.
     * Si c'est le cas, ajoute un message flash d'avertissement.
     *
     * @param int $budgetId L'ID du budget à vérifier
     * @param User $user L'utilisateur propriétaire du budget
     */
    public function checkAndNotify(int $budgetId, User $user): void
    {
        $connection = $this->entityManager->getConnection();

        // Récupérer les informations du budget
        $budget = $connection->fetchAssociative(
            'SELECT montant_total, nom_budget FROM budget WHERE id_budget = :id AND user_id = :user_id',
            ['id' => $budgetId, 'user_id' => $user->getId()]
        );

        if (!$budget) {
            return;
        }

        // Calculer le total des dépenses pour ce budget
        $totalSpent = (float) $connection->fetchOne(
            'SELECT COALESCE(SUM(montant), 0) FROM depense WHERE id_budget = :id',
            ['id' => $budgetId]
        );

        $limit = (float) $budget['montant_total'];
        
        if ($limit > 0) {
            $ratio = $totalSpent / $limit;
            
            if ($ratio >= 0.8) {
                $percentage = round($ratio * 100);
                $type = $ratio >= 1.0 ? 'danger' : 'warning';
                $icon = $ratio >= 1.0 ? '🚫' : '⚠️';
                
                $message = sprintf(
                    '%s Alerte Budget : Votre budget "%s" a atteint %d%% de sa capacité (%.2f / %.2f TND).',
                    $icon,
                    $budget['nom_budget'],
                    $percentage,
                    $totalSpent,
                    $limit
                );

                $session = $this->requestStack->getSession();
                
                // Éviter de répéter le même message s'il est déjà présent
                $existingFlashes = $session->getFlashBag()->peek($type);
                if (!in_array($message, $existingFlashes, true)) {
                    $session->getFlashBag()->add($type, $message);
                }
            }
        }
    }
}