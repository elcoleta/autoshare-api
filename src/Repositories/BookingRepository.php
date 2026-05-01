<?php

namespace App\Repositories;

use App\Framework\Database;

class BookingRepository
{
    public function hasOverlap(int $carId, string $startDate, string $endDate): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM bookings
            WHERE car_id = :car_id
              AND status = "confirmed"
              AND NOT (end_date < :start_date OR start_date > :end_date)
        ');
        $stmt->execute([
            'car_id' => $carId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function paginateForUser(int $userId, array $filters): array
    {
        $pdo = Database::connection();
        $where = ['b.user_id = :user_id'];
        $params = ['user_id' => $userId];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'b.status = :status';
            $params['status'] = $status;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(20, max(1, (int)($filters['per_page'] ?? 6)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.start_date,
                b.end_date,
                b.total_price,
                b.status,
                b.created_at,
                c.id AS car_id,
                c.brand,
                c.model,
                c.location,
                c.owner_id,
                u.name AS owner_name
            FROM bookings b
            JOIN cars c ON c.id = b.car_id
            LEFT JOIN users u ON u.id = c.owner_id
            {$whereSql}
            ORDER BY b.created_at DESC
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

    public function create(int $carId, int $userId, string $startDate, string $endDate, float $totalPrice): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO bookings (car_id, user_id, start_date, end_date, status, total_price)
            VALUES (:car_id, :user_id, :start_date, :end_date, "confirmed", :total_price)
        ');
        $stmt->execute([
            'car_id' => $carId,
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_price' => $totalPrice,
        ]);

        return $this->findById((int)$pdo->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT id, car_id, user_id, start_date, end_date, status, total_price, created_at
            FROM bookings
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);

        $booking = $stmt->fetch();
        return $booking ?: null;
    }

    public function cancel(int $id): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE bookings SET status = "cancelled" WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $this->findById($id) ?? [];
    }
}
