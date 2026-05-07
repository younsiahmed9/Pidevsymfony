<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BudgetManagementService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Calcule le solde total de tous les comptes de l'utilisateur.
     */
    public function getTotalUserBalance(User $user): float
    {
        return (float) $this->entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(solde), 0) FROM compte WHERE user_id = :user_id AND etat = "actif"',
            ['user_id' => $user->getId()]
        );
    }

    /**
     * Calcule le montant total déjà alloué aux budgets actifs.
     */
    public function getTotalAllocatedBudget(User $user, ?int $excludeBudgetId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(montant_total), 0) FROM budget WHERE user_id = :user_id AND statut = "actif"';
        $params = ['user_id' => $user->getId()];
        
        if ($excludeBudgetId) {
            $sql .= ' AND id_budget != :exclude_id';
            $params['exclude_id'] = $excludeBudgetId;
        }

        return (float) $this->entityManager->getConnection()->fetchOne($sql, $params);
    }

    /**
     * Récupère la liste des budgets avec leur montant disponible pour une éventuelle réallocation.
     */
    public function getAvailableBudgetsForReallocation(User $user): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT b.id_budget, b.nom_budget, b.montant_total,
                    (SELECT COALESCE(SUM(d.montant), 0) FROM depense d WHERE d.id_budget = b.id_budget) as total_depense
             FROM budget b
             WHERE b.user_id = :user_id AND b.statut = "actif"
             ORDER BY b.nom_budget ASC',
            ['user_id' => $user->getId()]
        );
    }

    /**
     * Réalloue une somme d'un budget vers un autre.
     */
    public function reallocate(int $fromBudgetId, int $toBudgetId, float $amount, User $user): bool
    {
        $connection = $this->entityManager->getConnection();
        
        // Vérification du budget source
        $sourceBudget = $connection->fetchAssociative(
            'SELECT montant_total, 
                    (SELECT COALESCE(SUM(montant), 0) FROM depense d WHERE d.id_budget = budget.id_budget) as total_depense 
             FROM budget WHERE id_budget = :id AND user_id = :user_id',
            ['id' => $fromBudgetId, 'user_id' => $user->getId()]
        );

        if (!$sourceBudget) return false;

        $sourceCurrentAmount = (float) $sourceBudget['montant_total'];
        $sourceSpent = (float) $sourceBudget['total_depense'];

        // On ne peut pas prélever plus que le disponible (Total - Dépenses)
        if ($sourceCurrentAmount - $amount < $sourceSpent) {
            return false;
        }

        // Vérification du budget cible
        $targetBudget = $connection->fetchAssociative(
            'SELECT montant_total FROM budget WHERE id_budget = :id AND user_id = :user_id',
            ['id' => $toBudgetId, 'user_id' => $user->getId()]
        );

        if (!$targetBudget) return false;

        $targetCurrentAmount = (float) $targetBudget['montant_total'];

        try {
            $connection->beginTransaction();

            // Mettre à jour le budget source
            $connection->update('budget', 
                ['montant_total' => $sourceCurrentAmount - $amount], 
                ['id_budget' => $fromBudgetId]
            );

            // Mettre à jour le budget cible
            $connection->update('budget', 
                ['montant_total' => $targetCurrentAmount + $amount], 
                ['id_budget' => $toBudgetId]
            );

            $connection->commit();
            return true;
        } catch (\Exception $e) {
            $connection->rollBack();
            return false;
        }
    }
}