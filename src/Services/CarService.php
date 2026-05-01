<?php

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Framework\ApiAuth;
use App\Framework\Request;
use App\Framework\Roles;
use App\Repositories\BookingRepository;
use App\Repositories\CarRepository;

class CarService
{
    private CarRepository $cars;
    private BookingRepository $bookings;

    public function __construct()
    {
        $this->cars = new CarRepository();
        $this->bookings = new BookingRepository();
    }

    public function paginate(Request $request): array
    {
        $result = $this->cars->paginate([
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 6),
            'search' => $request->query('search', ''),
            'location' => $request->query('location', ''),
            'min_price' => $request->query('min_price', ''),
            'max_price' => $request->query('max_price', ''),
            'available_from' => $request->query('available_from', ''),
            'available_to' => $request->query('available_to', ''),
            'owner_id' => $request->query('owner_id', 0),
        ]);

        return [
            'data' => $result['items'],
            'meta' => $this->paginationMeta($result),
        ];
    }

    public function getById(int $id): array
    {
        if ($id <= 0) {
            throw new ValidationException('Invalid car id.');
        }

        $car = $this->cars->findById($id);
        if ($car === null) {
            throw new NotFoundException('Car not found.');
        }

        return $car;
    }

    public function create(array $payload): array
    {
        $user = ApiAuth::requireRole([Roles::OWNER, Roles::ADMIN]);
        $data = $this->validatePayload($payload);
        $data['owner_id'] = (int)$user['id'];

        return $this->cars->create($data);
    }

    public function update(int $carId, array $payload): array
    {
        $car = $this->getById($carId);
        $user = ApiAuth::requireRole([Roles::OWNER, Roles::ADMIN]);

        if (($user['role'] ?? '') !== Roles::ADMIN && (int)$car['owner_id'] !== (int)$user['id']) {
            throw new ForbiddenException('You can only edit your own car listings.');
        }

        return $this->cars->update($carId, $this->validatePayload($payload, $car));
    }

    public function delete(int $carId): void
    {
        $car = $this->getById($carId);
        $user = ApiAuth::requireRole([Roles::OWNER, Roles::ADMIN]);

        if (($user['role'] ?? '') !== Roles::ADMIN && (int)$car['owner_id'] !== (int)$user['id']) {
            throw new ForbiddenException('You can only delete your own car listings.');
        }

        $this->cars->delete($carId);
    }

    public function checkAvailability(int $carId, string $startDate, string $endDate): bool
    {
        $this->getById($carId);
        [$start, $end] = $this->validateDateRange($startDate, $endDate);

        return !$this->bookings->hasOverlap($carId, $start, $end);
    }

    public function validateDateRange(string $startDate, string $endDate): array
    {
        $startDate = trim($startDate);
        $endDate = trim($endDate);

        if ($startDate === '' || $endDate === '') {
            throw new ValidationException('Choose a start and end date.');
        }

        $start = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);

        if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
            throw new ValidationException('Dates must use the YYYY-MM-DD format.');
        }

        if ($startDate > $endDate) {
            throw new ValidationException('Start date must be before end date.');
        }

        if ($startDate < date('Y-m-d')) {
            throw new ValidationException('Start date cannot be in the past.');
        }

        return [$startDate, $endDate];
    }

    private function validatePayload(array $payload, ?array $existing = null): array
    {
        $brand = trim((string)($payload['brand'] ?? $existing['brand'] ?? ''));
        $model = trim((string)($payload['model'] ?? $existing['model'] ?? ''));
        $location = trim((string)($payload['location'] ?? $existing['location'] ?? ''));
        $description = trim((string)($payload['description'] ?? $existing['description'] ?? ''));
        $pricePerDay = trim((string)($payload['price_per_day'] ?? $existing['price_per_day'] ?? ''));
        $imageUrl = trim((string)($payload['image_url'] ?? $existing['image_url'] ?? ''));

        if ($brand === '' || $model === '' || $location === '' || $pricePerDay === '') {
            throw new ValidationException('Brand, model, location and price are required.');
        }

        if (!is_numeric($pricePerDay) || (float)$pricePerDay <= 0) {
            throw new ValidationException('Price per day must be a positive number.');
        }

        if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL) && !str_starts_with($imageUrl, '/uploads/')) {
            throw new ValidationException('Image URL must be a valid URL or uploaded path.');
        }

        return [
            'brand' => $brand,
            'model' => $model,
            'location' => $location,
            'description' => $description,
            'price_per_day' => (float)$pricePerDay,
            'image_url' => $imageUrl === '' ? null : $imageUrl,
        ];
    }

    private function paginationMeta(array $result): array
    {
        return [
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
            'total_pages' => (int)max(1, ceil($result['total'] / $result['per_page'])),
        ];
    }
}
