<?php

namespace App\Repository;

use App\Entity\ArchiveRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ArchiveRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArchiveRecord::class);
    }

    public function findByDocument(int $documentId): ?ArchiveRecord
    {
        return $this->createQueryBuilder('a')
            ->where('a.document = :documentId')
            ->setParameter('documentId', $documentId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLegalArchivesRequiringPurge(\DateTimeInterface $before): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.archiveType = :type')
            ->andWhere('a.retentionUntil IS NOT NULL')
            ->andWhere('a.retentionUntil <= :before')
            ->setParameter('type', ArchiveRecord::TYPE_LEGAL)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }

    public function getArchiveStats(int $userId): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) as total, a.archiveType')
            ->join('a.document', 'd')
            ->where('d.utilisateur = :userId')
            ->groupBy('a.archiveType')
            ->setParameter('userId', $userId);

        return $qb->getQuery()->getResult();
    }
}