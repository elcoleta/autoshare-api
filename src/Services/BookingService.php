<?php

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Framework\ApiAuth;
use App\Framework\Request;
use App\Repositories\BookingRepository;
use App\Repositories\CarRepository;

class BookingService
{
    private BookingRepository $bookings;
    private CarRepository $cars;
    private CarService $carService;

    public function __construct()
    {
        $this->bookings = new BookingRepository();
        $this->cars = new CarRepository();
        $this->carService = new CarService();
    }

    public function paginate(Request $request): array
    {
        $user = ApiAuth::requireUser();
        $result = $this->bookings->paginateForUser((int)$user['id'], [
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 6),
            'status' => $request->query('status', ''),
        ]);

        return [
            'data' => $result['items'],
            'meta' => [
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'total_pages' => (int)max(1, ceil($result['total'] / $result['per_page'])),
            ],
        ];
    }

    public function create(array $payload): array
    {
        $user = ApiAuth::requireUser();
        $carId = (int)($payload['car_id'] ?? 0);
        $car = $this->cars->findById($carId);

        if ($car === null) {
            throw new NotFoundException('Car not found.');
        }

        if ((int)$car['owner_id'] === (int)$user['id']) {
            throw new ForbiddenException('You cannot book your own car.');
        }

        [$startDate, $endDate] = $this->carService->validateDateRange(
            (string)($payload['start_date'] ?? ''),
            (string)($payload['end_date'] ?? '')
        );

        if ($this->bookings->hasOverlap($carId, $startDate, $endDate)) {
            throw new ValidationException('This car is not available in that period.');
        }

        $days = $this->daysInclusive($startDate, $endDate);
        $totalPrice = ((float)$car['price_per_day']) * $days;

        return $this->bookings->create($carId, (int)$user['id'], $startDate, $endDate, $totalPrice);
    }

    public function cancel(int $bookingId): array
    {
        $user = ApiAuth::requireUser();
        $booking = $this->bookings->findById($bookingId);

        if ($booking === null) {
            throw new NotFoundException('Booking not found.');
        }

        $isOwner = (int)$booking['user_id'] === (int)$user['id'];
        $isAdmin = ($user['role'] ?? '') === 'admin';

        if (!$isOwner && !$isAdmin) {
            throw new ForbiddenException('You cannot cancel this booking.');
        }

        return $this->bookings->cancel($bookingId);
    }

    private function daysInclusive(string $startDate, string $endDate): int
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        return (int)$start->diff($end)->days + 1;
    }
}
