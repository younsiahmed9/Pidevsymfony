<?php
namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function search(string $query = '', string $type = '', string $statut = '', ?int $categorieId = null, ?int $dossierId = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.utilisateur', 'u')
            ->leftJoin('d.categorie', 'cat')
            ->leftJoin('d.dossier', 'dos')
            ->addSelect('u', 'cat', 'dos');

        if ($query) {
            $qb->andWhere('d.titre LIKE :q OR d.tags LIKE :q OR u.nom LIKE :q OR u.prenom LIKE :q OR d.typeDocument LIKE :q')
               ->setParameter('q', '%'.$query.'%');
        }
        if ($type) {
            $qb->andWhere('d.typeDocument = :type')->setParameter('type', $type);
        }
        if ($statut) {
            $qb->andWhere('d.statut = :statut')->setParameter('statut', $statut);
        }
        if ($categorieId) {
            $qb->andWhere('cat.id = :cat')->setParameter('cat', $categorieId);
        }
        if ($dossierId) {
            $qb->andWhere('dos.id = :dos')->setParameter('dos', $dossierId);
        }

        return $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
    }

    public function findExpiringDocuments(int $days = 30): array
    {
        $dateLimit = new \DateTime('+' . $days . ' days');

        return $this->createQueryBuilder('d')
            ->where('d.dateEcheance IS NOT NULL')
            ->andWhere('d.dateEcheance <= :dateLimit')
            ->andWhere('d.dateEcheance >= :today')
            ->andWhere('d.statut IN (:statuts)')
            ->setParameter('dateLimit', $dateLimit)
            ->setParameter('today', new \DateTime())
            ->setParameter('statuts', ['valide', 'a_renouveler'])
            ->orderBy('d.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
