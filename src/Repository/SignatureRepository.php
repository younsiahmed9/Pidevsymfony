<?php

namespace App\Repository;

use App\Entity\Signature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SignatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signature::class);
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

    public function findPendingBySigner(int $signerId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.signer = :signerId')
            ->andWhere('s.status = :status')
            ->setParameter('signerId', $signerId)
            ->setParameter('status', Signature::STATUS_PENDING)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByProviderReference(string $reference): ?Signature
    {
        return $this->createQueryBuilder('s')
            ->where('s.providerReference = :reference')
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }
}