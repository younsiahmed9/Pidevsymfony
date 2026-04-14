<?php

namespace App\Repository;

use App\Entity\ChatMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
final class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * @return ChatMessage[]
     */
    public function findConversation(User $currentUser, User $contact): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('(m.sender = :currentUser AND m.recipient = :contact) OR (m.sender = :contact AND m.recipient = :currentUser)')
            ->setParameter('currentUser', $currentUser)
            ->setParameter('contact', $contact)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function markConversationAsRead(User $currentUser, User $contact): int
    {
        return $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', ':isRead')
            ->andWhere('m.sender = :contact')
            ->andWhere('m.recipient = :currentUser')
            ->andWhere('m.isRead = :currentlyUnread')
            ->setParameter('isRead', true)
            ->setParameter('currentlyUnread', false)
            ->setParameter('contact', $contact)
            ->setParameter('currentUser', $currentUser)
            ->getQuery()
            ->execute();
    }

    public function countUnreadFromContact(User $currentUser, User $contact): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.sender = :contact')
            ->andWhere('m.recipient = :currentUser')
            ->andWhere('m.isRead = :isRead')
            ->setParameter('contact', $contact)
            ->setParameter('currentUser', $currentUser)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastMessageWithContact(User $currentUser, User $contact): ?ChatMessage
    {
        return $this->createQueryBuilder('m')
            ->andWhere('(m.sender = :currentUser AND m.recipient = :contact) OR (m.sender = :contact AND m.recipient = :currentUser)')
            ->setParameter('currentUser', $currentUser)
            ->setParameter('contact', $contact)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}