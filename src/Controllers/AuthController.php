<?php

namespace App\Controllers;

use App\Framework\ApiAuth;
use App\Framework\Jwt;
use App\Services\UserService;

class AuthController extends ApiController
{
    public function register(array $vars = []): void
    {
        $this->respond(function () {
            $service = new UserService();
            $user = $service->register($this->request()->all());

            return [
                'status' => 201,
                'data' => [
                    'token' => Jwt::encode(['sub' => $user['id'], 'role' => $user['role']]),
                    'user' => $user,
                ],
            ];
        });
    }

    public function login(array $vars = []): void
    {
        $this->respond(function () {
            $service = new UserService();
            $user = $service->login($this->request()->all());

            return [
                'data' => [
                    'token' => Jwt::encode(['sub' => $user['id'], 'role' => $user['role']]),
                    'user' => $user,
                ],
            ];
        });
    }

    public function forgotPassword(array $vars = []): void
    {
        $this->respond(fn () => [
            'data' => (new UserService())->requestPasswordReset($this->request()->all()),
        ]);
    }

    public function resetPassword(array $vars = []): void
    {
        $this->respond(function () {
            (new UserService())->resetPasswordWithToken($this->request()->all());

            return [
                'data' => ['updated' => true],
            ];
        });
    }

    public function me(array $vars = []): void
    {
        $this->respond(function () {
            return [
                'data' => ['user' => ApiAuth::requireUser()],
            ];
        });
    }
}
