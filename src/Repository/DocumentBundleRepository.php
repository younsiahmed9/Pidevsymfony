<?php

namespace App\Repository;

use App\Entity\DocumentBundle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentBundle>
 *
 * @method DocumentBundle|null find($id, $lockMode = null, $lockVersion = null)
 * @method DocumentBundle|null findOneBy(array $criteria, array $orderBy = null)
 * @method DocumentBundle[]    findAll()
 * @method DocumentBundle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DocumentBundleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentBundle::class);
    }

    /**
     * Trouve un bundle par son token de partage
     */
    public function findByToken(string $token): ?DocumentBundle
    {
        return $this->findOneBy(['tokenPartage' => $token]);
    }

    /**
     * Liste les bundles d'un utilisateur
     */
    public function findByUser($user): array
    {
        return $this->findBy(['utilisateur' => $user], ['createdAt' => 'DESC']);
    }
}
