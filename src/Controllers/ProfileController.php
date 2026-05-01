<?php

namespace App\Controllers;

use App\Services\UserService;

class ProfileController extends ApiController
{
    public function show(array $vars = []): void
    {
        $this->respond(fn () => [
            'data' => (new UserService())->profile(),
        ]);
    }

    public function update(array $vars = []): void
    {
        $this->respond(fn () => [
            'data' => (new UserService())->updateProfile($this->request()->all()),
        ]);
    }

    public function updatePassword(array $vars = []): void
    {
        $this->respond(function () {
            (new UserService())->updatePassword($this->request()->all());

            return [
                'data' => ['updated' => true],
            ];
        });
    }
}
