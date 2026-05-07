<?php

namespace App\Repository;

use App\Entity\Draft;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Draft::class);
    }

    public function findByUser(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.utilisateur = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('d.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findReadyToPublish(int $userId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.utilisateur = :userId')
            ->andWhere('d.isReady = true')
            ->setParameter('userId', $userId)
            ->orderBy('d.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}