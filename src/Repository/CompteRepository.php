<?php
namespace App\Repository;

use App\Entity\Compte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Compte::class);
    }

    public function search(string $query = '', string $type = '', string $etat = ''): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.utilisateur', 'u')
            ->addSelect('u');

        if ($query) {
            $qb->andWhere('c.numeroCompte LIKE :q OR u.nom LIKE :q OR u.prenom LIKE :q OR u.email LIKE :q')
               ->setParameter('q', '%'.$query.'%');
        }
        if ($type) {
            $qb->andWhere('c.typeCompte = :type')->setParameter('type', $type);
        }
        if ($etat) {
            $qb->andWhere('c.etat = :etat')->setParameter('etat', $etat);
        }

        return $qb->orderBy('c.dateCreation', 'DESC')->getQuery()->getResult();
    }
}
