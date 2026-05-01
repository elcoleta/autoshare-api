<?php

namespace App\Repositories;

use App\Framework\Database;

class CarRepository
{
    public function paginate(array $filters): array
    {
        $pdo = Database::connection();
        $where = [];
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(c.brand LIKE :search OR c.model LIKE :search OR c.location LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $location = trim((string)($filters['location'] ?? ''));
        if ($location !== '') {
            $where[] = 'c.location LIKE :location';
            $params['location'] = '%' . $location . '%';
        }

        $minPrice = trim((string)($filters['min_price'] ?? ''));
        if ($minPrice !== '' && is_numeric($minPrice)) {
            $where[] = 'c.price_per_day >= :min_price';
            $params['min_price'] = (float)$minPrice;
        }

        $maxPrice = trim((string)($filters['max_price'] ?? ''));
        if ($maxPrice !== '' && is_numeric($maxPrice)) {
            $where[] = 'c.price_per_day <= :max_price';
            $params['max_price'] = (float)$maxPrice;
        }

        $availableFrom = trim((string)($filters['available_from'] ?? ''));
        $availableTo = trim((string)($filters['available_to'] ?? ''));
        if ($availableFrom !== '' && $availableTo !== '') {
            $where[] = 'NOT EXISTS (
                SELECT 1
                FROM bookings b
                WHERE b.car_id = c.id
                  AND b.status = "confirmed"
                  AND NOT (b.end_date < :available_from OR b.start_date > :available_to)
            )';
            $params['available_from'] = $availableFrom;
            $params['available_to'] = $availableTo;
        }

        $ownerId = (int)($filters['owner_id'] ?? 0);
        if ($ownerId > 0) {
            $where[] = 'c.owner_id = :owner_id';
            $params['owner_id'] = $ownerId;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(24, max(1, (int)($filters['per_page'] ?? 6)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cars c {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.brand,
                c.model,
                c.location,
                c.description,
                c.price_per_day,
                c.image_url,
                c.owner_id,
                COALESCE(u.name, '') AS owner_name
            FROM cars c
            LEFT JOIN users u ON u.id = c.owner_id
            {$whereSql}
            ORDER BY c.id DESC
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

    public function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT
                c.id,
                c.brand,
                c.model,
                c.location,
                c.description,
                c.price_per_day,
                c.image_url,
                c.owner_id,
                COALESCE(u.name, "") AS owner_name
            FROM cars c
            LEFT JOIN users u ON u.id = c.owner_id
            WHERE c.id = :id
        ');
        $stmt->execute(['id' => $id]);

        $car = $stmt->fetch();
        return $car ?: null;
    }

    public function create(array $payload): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO cars (brand, model, location, description, price_per_day, image_url, owner_id)
            VALUES (:brand, :model, :location, :description, :price_per_day, :image_url, :owner_id)
        ');
        $stmt->execute($payload);

        return $this->findById((int)$pdo->lastInsertId());
    }

    public function update(int $id, array $payload): array
    {
        $pdo = Database::connection();
        $payload['id'] = $id;

        $stmt = $pdo->prepare('
            UPDATE cars
            SET brand = :brand,
                model = :model,
                location = :location,
                description = :description,
                price_per_day = :price_per_day,
                image_url = :image_url
            WHERE id = :id
        ');
        $stmt->execute($payload);

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM cars WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
