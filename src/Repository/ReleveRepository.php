<?php

namespace App\Repository;

use App\Entity\Releve;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Releve>
 *
 * @method Releve|null find($id, $lockMode = null, $lockVersion = null)
 * @method Releve|null findOneBy(array $criteria, array $orderBy = null)
 * @method Releve[]    findAll()
 * @method Releve[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReleveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Releve::class);
    }

    public function save(Releve $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Releve $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouver les relevés d'un compte triés par date décroissante
     */
    public function findByCompteOrderedByDate($compte)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.compte = :compte')
            ->setParameter('compte', $compte)
            ->orderBy('r.dateGeneration', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les relevés du dernier mois
     */
    public function findRecentReleves($compte, $days = 30)
    {
        $date = new \DateTime();
        $date->modify("-{$days} days");

        return $this->createQueryBuilder('r')
            ->andWhere('r.compte = :compte')
            ->andWhere('r.dateGeneration >= :date')
            ->setParameter('compte', $compte)
            ->setParameter('date', $date)
            ->orderBy('r.dateGeneration', 'DESC')
            ->getQuery()
            ->getResult();
    }
}