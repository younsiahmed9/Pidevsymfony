<?php
namespace App\Repository;

use App\Entity\Credit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Credit::class);
    }

    public function search(string $query = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('cr')
            ->leftJoin('cr.compte', 'c')
            ->addSelect('c');

        if ($query) {
            $qb->andWhere('c.numeroCompte LIKE :q')
               ->setParameter('q', '%'.$query.'%');
        }
        if ($status) {
            $qb->andWhere('cr.status = :status')->setParameter('status', $status);
        }

        return $qb->orderBy('cr.dateDebut', 'DESC')->getQuery()->getResult();
    }
}
