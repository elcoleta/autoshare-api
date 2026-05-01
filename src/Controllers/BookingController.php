<?php

namespace App\Controllers;

use App\Services\BookingService;

class BookingController extends ApiController
{
    public function index(array $vars = []): void
    {
        $this->respond(fn () => (new BookingService())->paginate($this->request()));
    }

    public function store(array $vars = []): void
    {
        $this->respond(fn () => [
            'status' => 201,
            'data' => (new BookingService())->create($this->request()->all()),
        ]);
    }

    public function update(array $vars = []): void
    {
        $this->respond(function () use ($vars) {
            $booking = (new BookingService())->cancel((int)($vars['id'] ?? 0));

            return [
                'data' => $booking,
            ];
        });
    }
}
