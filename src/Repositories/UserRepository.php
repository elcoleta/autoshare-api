<?php

namespace App\Repositories;

use App\Framework\Database;

class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT id, name, email, password_hash, role, created_at
            FROM users
            WHERE email = :email
        ');
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findPublicById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT id, name, email, role, created_at
            FROM users
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(string $name, string $email, string $hash, string $role): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO users (name, email, password_hash, role)
            VALUES (:name, :email, :password_hash, :role)
        ');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $hash,
            'role' => $role,
        ]);

        return $this->findPublicById((int)$pdo->lastInsertId());
    }

    public function updateProfile(int $id, string $name, string $email): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);

        return $this->findPublicById($id);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE users
            SET password_hash = :hash,
                reset_token_hash = NULL,
                reset_token_expires_at = NULL
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $id,
            'hash' => $hash,
        ]);
    }

    public function paginate(array $filters): array
    {
        $pdo = Database::connection();
        $where = [];
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(name LIKE :search OR email LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $role = trim((string)($filters['role'] ?? ''));
        if ($role !== '') {
            $where[] = 'role = :role';
            $params['role'] = $role;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(20, max(1, (int)($filters['per_page'] ?? 8)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT id, name, email, role, created_at
            FROM users
            {$whereSql}
            ORDER BY created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    public function updateRole(int $id, string $role): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'role' => $role,
        ]);

        return $this->findPublicById($id);
    }

    public function savePasswordResetToken(int $id, string $tokenHash, string $expiresAt): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            UPDATE users
            SET reset_token_hash = :token_hash,
                reset_token_expires_at = :expires_at
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $id,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findByResetTokenHash(string $tokenHash): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT id, name, email, password_hash, role, reset_token_hash, reset_token_expires_at, created_at
            FROM users
            WHERE reset_token_hash = :token_hash
              AND reset_token_expires_at IS NOT NULL
              AND reset_token_expires_at >= NOW()
        ');
        $stmt->execute(['token_hash' => $tokenHash]);

        $user = $stmt->fetch();
        return $user ?: null;
    }
}
