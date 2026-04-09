<?php

namespace App\Service;

use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;

class FactureService
{
    public function __construct(
        private FactureRepository $factureRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Récupère toutes les factures par statut
     */
    public function getFacturesByStatut(string $statut): array
    {
        return $this->factureRepository->findBy(['statut' => $statut]);
    }

    /**
     * Récupère toutes les factures expirées (date échéance passée et statut en_attente)
     */
    public function getFacturesExpires(): array
    {
        $all = $this->factureRepository->findAll();
        $expirees = [];

        foreach ($all as $facture) {
            if ($facture->isExpired()) {
                $expirees[] = $facture;
            }
        }

        return $expirees;
    }

    /**
     * Récupère les factures impayées
     */
    public function getFacturesImpayees(): array
    {
        return $this->factureRepository->findBy(['statut' => 'impayee']);
    }

    /**
     * Récupère les factures payées
     */
    public function getFacturesPayees(): array
    {
        return $this->factureRepository->findBy(['statut' => 'payee']);
    }

    /**
     * Compter les factures impayées
     */
    public function countFacturesImpayees(): int
    {
        return count($this->factureRepository->findBy(['statut' => 'impayee']));
    }

    /**
     * Compter les factures expirées
     */
    public function countFacturesExpires(): int
    {
        return count($this->getFacturesExpires());
    }

    /**
     * Compter les factures payées
     */
    public function countFacturesPayees(): int
    {
        return count($this->factureRepository->findBy(['statut' => 'payee']));
    }

    /**
     * Calculer le montant total des factures impayées
     */
    public function getTotalMontantImpaye(): float
    {
        $factures = $this->factureRepository->findBy(['statut' => 'impayee']);
        $total = 0;

        foreach ($factures as $facture) {
            $total += (float)$facture->getMontant();
        }

        return $total;
    }

    /**
     * Calculer le montant total de toutes les factures
     */
    public function getTotalMontantFactures(): float
    {
        $factures = $this->factureRepository->findAll();
        $total = 0;

        foreach ($factures as $facture) {
            $total += (float)$facture->getMontant();
        }

        return $total;
    }

    /**
     * Calculer le montant total des factures payées
     */
    public function getTotalMontantPayee(): float
    {
        $factures = $this->factureRepository->findBy(['statut' => 'payee']);
        $total = 0;

        foreach ($factures as $facture) {
            $total += (float)$facture->getMontant();
        }

        return $total;
    }

    /**
     * Calculer le montant total des factures expirées
     */
    public function getTotalMontantExpire(): float
    {
        $factures = $this->getFacturesExpires();
        $total = 0;

        foreach ($factures as $facture) {
            $total += (float)$facture->getMontant();
        }

        return $total;
    }

    /**
     * Récupère les factures par service
     */
    public function getFacturesByService(int $serviceId): array
    {
        return $this->factureRepository->createQueryBuilder('f')
            ->where('f.service = :serviceId')
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les factures par produit
     */
    public function getFacturesByProduit(int $produitId): array
    {
        return $this->factureRepository->createQueryBuilder('f')
            ->where('f.produit = :produitId')
            ->setParameter('produitId', $produitId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Mettre à jour le statut d'une facture
     */
    public function updateStatut(int $id, string $statut): bool
    {
        $facture = $this->factureRepository->find($id);
        if (!$facture) {
            return false;
        }

        $facture->setStatut($statut);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Calculer le taux de recouvrement (%)
     */
    public function getTauxRecouvrement(): float
    {
        $total = $this->getTotalMontantFactures();
        if ($total == 0) {
            return 0;
        }

        $payee = $this->getTotalMontantPayee();
        return ($payee / $total) * 100;
    }

    /**
     * Calculer le taux d'impayé (%)
     */
    public function getTauxImpaye(): float
    {
        return 100 - $this->getTauxRecouvrement();
    }
}
