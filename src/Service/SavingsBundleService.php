<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Compte;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour gérer le "Bundle d'Épargne Automatique".
 */
class SavingsBundleService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Récupère l'état actuel des budgets avec leur reste disponible.
     */
    public function getBudgetsResiduals(User $user): array
    {
        $connection = $this->entityManager->getConnection();
        return $connection->fetchAllAssociative(
            'SELECT b.id_budget, b.nom_budget, b.montant_total, 
                    (SELECT COALESCE(SUM(d.montant), 0) FROM depense d WHERE d.id_budget = b.id_budget) as total_depense
             FROM budget b
             WHERE b.user_id = :user_id AND b.statut = "actif"',
            ['user_id' => $user->getId()]
        );
    }

    /**
     * Exécute le regroupement des montants vers un compte épargne.
     */
    public function sweepToSavings(User $user, array $sweeps, ?int $targetAccountId = null): array
    {
        $results = ['success' => false, 'message' => '', 'total_swept' => 0.0];
        $connection = $this->entityManager->getConnection();
        $totalToSweep = 0.0;

        try {
            $connection->beginTransaction();

            // 1. Déduction des montants des budgets
            foreach ($sweeps as $sweep) {
                $amount = (float)$sweep['amount'];
                $budgetId = (int)$sweep['budget_id'];

                if ($amount <= 0) continue;

                // Vérifier si le budget a assez de reste
                $budget = $connection->fetchAssociative(
                    'SELECT b.nom_budget, b.montant_total, (SELECT COALESCE(SUM(d.montant), 0) FROM depense d WHERE d.id_budget = b.id_budget) as total_depense 
                     FROM budget b WHERE b.id_budget = :id AND b.user_id = :uid',
                    ['id' => $budgetId, 'uid' => $user->getId()]
                );

                if (!$budget) throw new \Exception("Budget ID $budgetId introuvable.");

                $residual = (float)$budget['montant_total'] - (float)$budget['total_depense'];
                if ($amount > $residual + 0.001) { // Marge d'arrondi
                    throw new \Exception("Le montant à épargner (" . number_format($amount, 2) . " TND) dépasse le reste disponible du budget " . $budget['nom_budget']);
                }

                // Réduction du montant total du budget
                $connection->executeStatement(
                    'UPDATE budget SET montant_total = montant_total - :amount WHERE id_budget = :id',
                    ['amount' => $amount, 'id' => $budgetId]
                );

                $totalToSweep += $amount;
            }

            if ($totalToSweep <= 0) {
                throw new \Exception("Aucun montant valide à transférer.");
            }

            // 2. Gestion du compte épargne
            if ($targetAccountId) {
                // Ajouter au compte existant
                $connection->executeStatement(
                    'UPDATE compte SET solde = solde + :amount WHERE id_compte = :id AND user_id = :uid',
                    ['amount' => $totalToSweep, 'id' => $targetAccountId, 'uid' => $user->getId()]
                );
                $results['message'] = sprintf("%.2f TND ont été ajoutés à votre compte épargne existant.", $totalToSweep);
            } else {
                // Créer un nouveau compte épargne
                $accountNum = 'EP-' . strtoupper(substr(uniqid(), -8));
                
                // On utilise l'ORM pour le nouveau compte pour respecter les validations
                $newAccount = new Compte();
                $newAccount->setUtilisateur($user);
                $newAccount->setNumeroCompte($accountNum);
                $newAccount->setTypeCompte('epargne');
                $newAccount->setSolde((string)$totalToSweep);
                $newAccount->setTauxInteret('2.50'); // Taux par défaut
                $newAccount->setEtat('actif');
                $this->entityManager->persist($newAccount);
                $this->entityManager->flush();
                
                $results['message'] = sprintf("Nouveau compte épargne %s ouvert avec %.2f TND.", $accountNum, $totalToSweep);
            }

            $connection->commit();
            $results['success'] = true;
            $results['total_swept'] = $totalToSweep;

        } catch (\Exception $e) {
            $connection->rollBack();
            $results['success'] = false;
            $results['message'] = $e->getMessage();
        }

        return $results;
    }
}
