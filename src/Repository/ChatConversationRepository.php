<?php

namespace App\Repository;

use App\Entity\ChatConversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatConversation>
 */
final class ChatConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatConversation::class);
    }

    public function findBetweenUsers(User $userA, User $userB): ?ChatConversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('(c.userA = :userA AND c.userB = :userB) OR (c.userA = :userB AND c.userB = :userA)')
            ->setParameter('userA', $userA)
            ->setParameter('userB', $userB)
            ->getQuery()
            ->getOneOrNullResult();
    }
}