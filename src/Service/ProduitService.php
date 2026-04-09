<?php

namespace App\Service;

use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProduitService
{
    public function __construct(
        private ProduitRepository $produitRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Récupère tous les produits par type
     */
    public function getProduitsByType(string $type): array
    {
        return $this->produitRepository->findBy(['typeProduit' => $type]);
    }

    /**
     * Récupère tous les produits par statut
     */
    public function getProduitsByStatut(string $statut): array
    {
        return $this->produitRepository->findBy(['statut' => $statut]);
    }

    /**
     * Compter les produits vendus
     */
    public function countProduitVendus(): int
    {
        return count($this->produitRepository->findBy(['statut' => 'vendu']));
    }

    /**
     * Compter les produits disponibles
     */
    public function countProduitDisponible(): int
    {
        return count($this->produitRepository->findBy(['statut' => 'disponible']));
    }

    /**
     * Compter les produits expirés
     */
    public function countProduitExpire(): int
    {
        return count($this->produitRepository->findBy(['statut' => 'expire']));
    }

    /**
     * Mettre à jour le statut d'un produit
     */
    public function updateStatut(int $id, string $statut): bool
    {
        $produit = $this->produitRepository->find($id);
        if (!$produit) {
            return false;
        }

        $produit->setStatut($statut);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Calculer le montant total des produits disponibles
     */
    public function getTotalMontantDisponible(): float
    {
        $produits = $this->produitRepository->findBy(['statut' => 'disponible']);
        $total = 0;

        foreach ($produits as $produit) {
            $total += (float)$produit->getMontant();
        }

        return $total;
    }
}
