<?php
namespace App\Repository;

use App\Entity\Echeance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EcheanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Echeance::class);
    }

    public function search(string $query = '', string $statut = '', string $dateFrom = '', string $dateTo = ''): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.document', 'd')
            ->leftJoin('e.utilisateur', 'u')
            ->addSelect('d', 'u');

        if ($query) {
                $qb->andWhere('e.titre LIKE :q OR d.titre LIKE :q OR u.fullName LIKE :q OR u.email LIKE :q')
               ->setParameter('q', '%'.$query.'%');
        }
        if ($statut) {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', $statut);
        }
        if ($dateFrom) {
            $qb->andWhere('e.dateEcheance >= :dateFrom')->setParameter('dateFrom', new \DateTime($dateFrom));
        }
        if ($dateTo) {
            $qb->andWhere('e.dateEcheance <= :dateTo')->setParameter('dateTo', new \DateTime($dateTo));
        }

        return $qb->orderBy('e.dateEcheance', 'ASC')->getQuery()->getResult();
    }

    public function findOverdue(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.dateEcheance < :today')
            ->andWhere('e.statut NOT IN (:done)')
            ->setParameter('today', new \DateTime())
            ->setParameter('done', ['completed', 'overdue'])
            ->orderBy('e.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
