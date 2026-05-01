<?php

namespace App\Controllers;

use App\Services\CarService;

class CarController extends ApiController
{
    public function index(array $vars = []): void
    {
        $this->respond(fn () => (new CarService())->paginate($this->request()));
    }

    public function show(array $vars = []): void
    {
        $this->respond(fn () => [
            'data' => (new CarService())->getById((int)($vars['id'] ?? 0)),
        ]);
    }

    public function store(array $vars = []): void
    {
        $this->respond(fn () => [
            'status' => 201,
            'data' => (new CarService())->create($this->request()->all()),
        ]);
    }

    public function update(array $vars = []): void
    {
        $this->respond(fn () => [
            'data' => (new CarService())->update((int)($vars['id'] ?? 0), $this->request()->all()),
        ]);
    }

    public function destroy(array $vars = []): void
    {
        $this->respond(function () use ($vars) {
            (new CarService())->delete((int)($vars['id'] ?? 0));

            return [
                'data' => ['deleted' => true],
            ];
        });
    }

    public function availability(array $vars = []): void
    {
        $this->respond(function () use ($vars) {
            return [
                'data' => [
                    'available' => (new CarService())->checkAvailability(
                        (int)($vars['id'] ?? 0),
                        (string)$this->request()->query('start', ''),
                        (string)$this->request()->query('end', '')
                    ),
                ],
            ];
        });
    }
}
