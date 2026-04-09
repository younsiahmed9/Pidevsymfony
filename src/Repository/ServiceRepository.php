<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * @return Service[]
     */
    public function findAdvanced(?string $search = null, ?string $type = null, ?string $status = null, ?string $sort = null, ?string $order = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if ($search) {
            $qb->andWhere('s.nomService LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($type) {
            $qb->andWhere('s.typeService = :type')
               ->setParameter('type', $type);
        }

        if ($status) {
            $qb->andWhere('s.statut = :status')
               ->setParameter('status', $status);
        }

        if ($sort) {
            $qb->orderBy('s.' . $sort, $order);
        } else {
            $qb->orderBy('s.id', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
}
