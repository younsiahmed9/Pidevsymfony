<?php

namespace App\Repository;

use App\Entity\Signatory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SignatoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signatory::class);
    }

    public function findByDocument(int $documentId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.document = :documentId')
            ->setParameter('documentId', $documentId)
            ->orderBy('s.signingOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findNextPendingSignatory(int $documentId): ?Signatory
    {
        return $this->createQueryBuilder('s')
            ->where('s.document = :documentId')
            ->andWhere('s.status = :status')
            ->setParameter('documentId', $documentId)
            ->setParameter('status', 'pending')
            ->orderBy('s.signingOrder', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUser(int $userId, string $status = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.user = :userId')
            ->setParameter('userId', $userId);

        if ($status !== null) {
            $qb->andWhere('s.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->orderBy('s.invitedAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}