<?php

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Framework\ApiAuth;
use App\Framework\Request;
use App\Framework\Roles;
use App\Repositories\UserRepository;

class UserService
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function register(array $payload): array
    {
        $name = trim((string)($payload['name'] ?? ''));
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $password = (string)($payload['password'] ?? '');
        $role = (string)($payload['role'] ?? Roles::CUSTOMER);

        if ($name === '' || $email === '' || $password === '') {
            throw new ValidationException('Name, email and password are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Enter a valid email address.');
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            throw new ValidationException('Name must be between 2 and 80 characters.');
        }

        if (strlen($password) < 6) {
            throw new ValidationException('Password must be at least 6 characters.');
        }

        if (!in_array($role, [Roles::CUSTOMER, Roles::OWNER], true)) {
            $role = Roles::CUSTOMER;
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new ValidationException('That email address is already in use.');
        }

        return $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT), $role);
    }

    public function login(array $payload): array
    {
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $password = (string)($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new ValidationException('Email and password are required.');
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new ValidationException('Incorrect email or password.');
        }

        unset($user['password_hash']);
        return $user;
    }

    public function profile(): array
    {
        return ApiAuth::requireUser();
    }

    public function updateProfile(array $payload): array
    {
        $user = ApiAuth::requireUser();
        $name = trim((string)($payload['name'] ?? ''));
        $email = strtolower(trim((string)($payload['email'] ?? '')));

        if ($name === '' || $email === '') {
            throw new ValidationException('Name and email are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Enter a valid email address.');
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null && (int)$existing['id'] !== (int)$user['id']) {
            throw new ValidationException('That email address is already in use.');
        }

        return $this->users->updateProfile((int)$user['id'], $name, $email);
    }

    public function updatePassword(array $payload): void
    {
        $user = ApiAuth::requireUser();
        $currentPassword = (string)($payload['current_password'] ?? '');
        $newPassword = (string)($payload['new_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            throw new ValidationException('Both password fields are required.');
        }

        if (strlen($newPassword) < 6) {
            throw new ValidationException('New password must be at least 6 characters.');
        }

        $storedUser = $this->users->findByEmail((string)$user['email']);
        if ($storedUser === null || !password_verify($currentPassword, $storedUser['password_hash'])) {
            throw new ValidationException('Current password is incorrect.');
        }

        $this->users->updatePassword((int)$user['id'], password_hash($newPassword, PASSWORD_DEFAULT));
    }

    public function requestPasswordReset(array $payload): array
    {
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        if ($email === '') {
            throw new ValidationException('Email is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Enter a valid email address.');
        }

        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return [
                'message' => 'If the account exists, a reset request has been created.',
            ];
        }

        $token = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $this->users->savePasswordResetToken((int)$user['id'], $tokenHash, $expiresAt);

        return [
            'message' => 'Password reset requested.',
            'reset_token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function resetPasswordWithToken(array $payload): void
    {
        $token = trim((string)($payload['token'] ?? ''));
        $newPassword = (string)($payload['new_password'] ?? '');

        if ($token === '' || $newPassword === '') {
            throw new ValidationException('Token and new password are required.');
        }

        if (strlen($newPassword) < 6) {
            throw new ValidationException('New password must be at least 6 characters.');
        }

        $user = $this->users->findByResetTokenHash(hash('sha256', $token));
        if ($user === null) {
            throw new ValidationException('This password reset token is invalid or expired.');
        }

        $this->users->updatePassword((int)$user['id'], password_hash($newPassword, PASSWORD_DEFAULT));
    }

    public function paginateUsers(Request $request): array
    {
        ApiAuth::requireRole([Roles::ADMIN]);

        $result = $this->users->paginate([
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 8),
            'search' => $request->query('search', ''),
            'role' => $request->query('role', ''),
        ]);

        return [
            'data' => $result['items'],
            'meta' => $this->paginationMeta($result),
        ];
    }

    public function updateUser(int $id, array $payload): array
    {
        $currentUser = ApiAuth::requireRole([Roles::ADMIN]);
        $targetUser = $this->users->findPublicById($id);
        if ($targetUser === null) {
            throw new NotFoundException('User not found.');
        }

        $role = (string)($payload['role'] ?? '');
        if (!in_array($role, Roles::all(), true)) {
            throw new ValidationException('Invalid role selected.');
        }

        if ((int)$currentUser['id'] === $id && $role !== Roles::ADMIN) {
            throw new ForbiddenException('You cannot remove your own admin role.');
        }

        return $this->users->updateRole($id, $role);
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
