<?php

namespace App\Service;

use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;

class ServiceService
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Récupère tous les services par type
     */
    public function getServicesByType(string $type): array
    {
        return $this->serviceRepository->findBy(['typeService' => $type]);
    }

    /**
     * Récupère tous les services par statut
     */
    public function getServicesByStatut(string $statut): array
    {
        return $this->serviceRepository->findBy(['statut' => $statut]);
    }

    /**
     * Récupère tous les services actifs
     */
    public function getServicesActifs(): array
    {
        return $this->serviceRepository->findBy(['statut' => 'actif']);
    }

    /**
     * Compter les services actifs
     */
    public function countServicesActifs(): int
    {
        return count($this->serviceRepository->findBy(['statut' => 'actif']));
    }

    /**
     * Compter les services suspendus
     */
    public function countServicesSuspendus(): int
    {
        return count($this->serviceRepository->findBy(['statut' => 'suspendu']));
    }

    /**
     * Compter les services expirés
     */
    public function countServicesExpires(): int
    {
        return count($this->serviceRepository->findBy(['statut' => 'expire']));
    }

    /**
     * Mettre à jour le statut d'un service
     */
    public function updateStatut(int $id, string $statut): bool
    {
        $service = $this->serviceRepository->find($id);
        if (!$service) {
            return false;
        }

        $service->setStatut($statut);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Calculer le tarif total des services actifs
     */
    public function getTotalTarifActif(): float
    {
        $services = $this->serviceRepository->findBy(['statut' => 'actif']);
        $total = 0;

        foreach ($services as $service) {
            $total += (float)$service->getTarif();
        }

        return $total;
    }

    /**
     * Calculer la moyenne des tarifs
     */
    public function getAverageTarif(): float
    {
        $services = $this->serviceRepository->findAll();
        if (count($services) === 0) {
            return 0;
        }

        $total = 0;
        foreach ($services as $service) {
            $total += (float)$service->getTarif();
        }

        return $total / count($services);
    }
}
