<?php

namespace App\Repository;

use App\Entity\UserServiceInteraction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserServiceInteraction>
 */
class UserServiceInteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserServiceInteraction::class);
    }

    public function findByUserAndService(int $userId, int $serviceId): ?UserServiceInteraction
    {
        return $this->createQueryBuilder('i')
            ->where('i.user = :userId')
            ->andWhere('i.service = :serviceId')
            ->setParameter('userId', $userId)
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRecentInteractions(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.user = :userId')
            ->orderBy('i.createdAt', 'DESC')
            ->setParameter('userId', $userId)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPopularServices(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->select('s.id, s.nomService, COUNT(i.id) as interaction_count, AVG(i.rating) as avg_rating')
            ->join('i.service', 's')
            ->where('s.statut = :status')
            ->setParameter('status', 'actif')
            ->groupBy('s.id, s.nomService')
            ->orderBy('interaction_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByInteractionType(string $interactionType, int $limit = 100): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.interactionType = :type')
            ->setParameter('type', $interactionType)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
