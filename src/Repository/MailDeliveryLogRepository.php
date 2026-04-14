<?php

namespace App\Repository;

use App\Entity\MailDeliveryLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailDeliveryLog>
 */
final class MailDeliveryLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailDeliveryLog::class);
    }

    /**
     * @return MailDeliveryLog[]
     */
    public function findRecentForUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user OR l.recipientEmail = :email')
            ->setParameter('user', $user)
            ->setParameter('email', (string) $user->getEmail())
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MailDeliveryLog[]
     */
    public function findRecentGlobal(int $limit = 250): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}