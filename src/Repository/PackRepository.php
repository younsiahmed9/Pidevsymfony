<?php

namespace App\Repository;

use App\Entity\Pack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pack>
 *
 * @method Pack|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pack|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pack[]    findAll()
 * @method Pack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pack::class);
    }

    /**
     * Trouve un pack par son token de partage
     */
    public function findByToken(string $token): ?Pack
    {
        return $this->findOneBy(['tokenPartage' => $token]);
    }

    /**
     * Liste les packs d'un utilisateur
     */
    public function findByUser($user): array
    {
        return $this->findBy(['utilisateur' => $user], ['createdAt' => 'DESC']);
    }
}
