<?php

namespace App\Controllers;

class HealthController extends ApiController
{
    public function show(array $vars = []): void
    {
        $this->respond(fn () => [
            'data' => ['ok' => true],
        ]);
    }
}
