<?php

namespace App\WebSocket;

use Doctrine\DBAL\Connection;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

final class ChatMessageNotifier implements MessageComponentInterface
{
    private SplObjectStorage $clients;

    /** @var array<int, array<int, ConnectionInterface>> */
    private array $clientsByUserId = [];

    private int $lastSeenMessageId = 0;

    public function __construct(
        private Connection $connection,
    ) {
        $this->clients = new SplObjectStorage();
        $this->initializeLastSeenMessageId();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);

        $userId = (int) ($params['userId'] ?? 0);
        if ($userId <= 0) {
            $conn->close();
            return;
        }

        $this->clients->attach($conn, $userId);

        if (!isset($this->clientsByUserId[$userId])) {
            $this->clientsByUserId[$userId] = [];
        }

        $this->clientsByUserId[$userId][spl_object_id($conn)] = $conn;
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Keep protocol simple: server push only.
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if (!$this->clients->contains($conn)) {
            return;
        }

        $userId = (int) $this->clients[$conn];
        $this->clients->detach($conn);

        if (isset($this->clientsByUserId[$userId])) {
            unset($this->clientsByUserId[$userId][spl_object_id($conn)]);
            if ($this->clientsByUserId[$userId] === []) {
                unset($this->clientsByUserId[$userId]);
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $conn->close();
    }

    public function tick(): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, sender_id, recipient_id, body, DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") AS created_at_fmt
             FROM chat_messages
             WHERE id > :lastSeen
             ORDER BY id ASC
             LIMIT 200',
            ['lastSeen' => $this->lastSeenMessageId],
        );

        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $messageId = (int) ($row['id'] ?? 0);
            if ($messageId <= 0) {
                continue;
            }

            $this->lastSeenMessageId = max($this->lastSeenMessageId, $messageId);

            $senderId = (int) ($row['sender_id'] ?? 0);
            $recipientId = (int) ($row['recipient_id'] ?? 0);
            if ($senderId <= 0 || $recipientId <= 0) {
                continue;
            }

            $payload = [
                'type' => 'chat.message.created',
                'message' => [
                    'id' => $messageId,
                    'senderId' => $senderId,
                    'recipientId' => $recipientId,
                    'body' => (string) ($row['body'] ?? ''),
                    'createdAt' => (string) ($row['created_at_fmt'] ?? ''),
                ],
            ];

            $this->publishToUser($senderId, $payload);
            if ($recipientId !== $senderId) {
                $this->publishToUser($recipientId, $payload);
            }
        }
    }

    private function initializeLastSeenMessageId(): void
    {
        $lastId = $this->connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM chat_messages');
        $this->lastSeenMessageId = (int) $lastId;
    }

    private function publishToUser(int $userId, array $payload): void
    {
        $connections = $this->clientsByUserId[$userId] ?? [];
        if ($connections === []) {
            return;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        foreach ($connections as $connection) {
            $connection->send($encoded);
        }
    }
}
