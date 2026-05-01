<?php

namespace App\Controllers;

use App\Services\UserService;

class UserController extends ApiController
{
    public function index(array $vars = []): void
    {
        $this->respond(fn () => (new UserService())->paginateUsers($this->request()));
    }

    public function update(array $vars = []): void
    {
        $this->respond(fn () => [
            'data' => (new UserService())->updateUser((int)($vars['id'] ?? 0), $this->request()->all()),
        ]);
    }
}
