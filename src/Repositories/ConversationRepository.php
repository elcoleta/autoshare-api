<?php

namespace App\Repositories;

use App\Framework\Database;

class ConversationRepository
{
    public function paginateForUser(int $userId, array $filters): array
    {
        $pdo = Database::connection();
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(20, max(1, (int)($filters['per_page'] ?? 8)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM conversations
            WHERE user_one_id = :user_id OR user_two_id = :user_id
        ');
        $countStmt->execute(['user_id' => $userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.created_at,
                CASE
                    WHEN c.user_one_id = :user_id THEN c.user_two_id
                    ELSE c.user_one_id
                END AS participant_id,
                CASE
                    WHEN c.user_one_id = :user_id THEN user_two.name
                    ELSE user_one.name
                END AS participant_name,
                latest.body AS latest_message,
                latest.created_at AS latest_message_at,
                latest.sender_id AS latest_sender_id
            FROM conversations c
            JOIN users user_one ON user_one.id = c.user_one_id
            JOIN users user_two ON user_two.id = c.user_two_id
            LEFT JOIN messages latest ON latest.id = (
                SELECT m.id
                FROM messages m
                WHERE m.conversation_id = c.id
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1
            )
            WHERE c.user_one_id = :user_id OR c.user_two_id = :user_id
            ORDER BY COALESCE(latest.created_at, c.created_at) DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute(['user_id' => $userId]);

        return [
            'items' => $stmt->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    public function findBetweenUsers(int $userA, int $userB): ?array
    {
        $pdo = Database::connection();
        $small = min($userA, $userB);
        $large = max($userA, $userB);

        $stmt = $pdo->prepare('
            SELECT id, user_one_id, user_two_id, created_at
            FROM conversations
            WHERE user_one_id = :user_one_id AND user_two_id = :user_two_id
        ');
        $stmt->execute([
            'user_one_id' => $small,
            'user_two_id' => $large,
        ]);

        $conversation = $stmt->fetch();
        return $conversation ?: null;
    }

    public function create(int $userA, int $userB): array
    {
        $pdo = Database::connection();
        $small = min($userA, $userB);
        $large = max($userA, $userB);

        $stmt = $pdo->prepare('
            INSERT INTO conversations (user_one_id, user_two_id)
            VALUES (:user_one_id, :user_two_id)
        ');
        $stmt->execute([
            'user_one_id' => $small,
            'user_two_id' => $large,
        ]);

        return $this->findById((int)$pdo->lastInsertId()) ?? [];
    }

    public function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT id, user_one_id, user_two_id, created_at
            FROM conversations
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);

        $conversation = $stmt->fetch();
        return $conversation ?: null;
    }

    public function paginateMessages(int $conversationId, array $filters): array
    {
        $pdo = Database::connection();
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(40, max(1, (int)($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE conversation_id = :conversation_id');
        $countStmt->execute(['conversation_id' => $conversationId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
                m.id,
                m.sender_id,
                u.name AS sender_name,
                m.body,
                m.created_at
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.conversation_id = :conversation_id
            ORDER BY m.created_at ASC, m.id ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute(['conversation_id' => $conversationId]);

        return [
            'items' => $stmt->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    public function addMessage(int $conversationId, int $senderId, string $body): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO messages (conversation_id, sender_id, body)
            VALUES (:conversation_id, :sender_id, :body)
        ');
        $stmt->execute([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'body' => $body,
        ]);

        $messageId = (int)$pdo->lastInsertId();
        $messageStmt = $pdo->prepare('
            SELECT m.id, m.sender_id, u.name AS sender_name, m.body, m.created_at
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.id = :id
        ');
        $messageStmt->execute(['id' => $messageId]);

        return $messageStmt->fetch() ?: [];
    }
}
