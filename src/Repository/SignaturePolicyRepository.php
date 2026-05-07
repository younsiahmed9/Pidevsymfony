<?php

namespace App\Repository;

use App\Entity\SignaturePolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SignaturePolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SignaturePolicy::class);
    }

    public function findDefault(): ?SignaturePolicy
    {
        return $this->createQueryBuilder('p')
            ->where('p.isDefault = true')
            ->andWhere('p.isActive = true')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.type = :type')
            ->andWhere('p.isActive = true')
            ->setParameter('type', $type)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}