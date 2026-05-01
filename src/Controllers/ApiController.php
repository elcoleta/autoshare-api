<?php

namespace App\Controllers;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Framework\JsonResponse;
use App\Framework\Request;

abstract class ApiController
{
    protected function request(): Request
    {
        return Request::capture();
    }

    protected function respond(callable $callback): void
    {
        try {
            $result = $callback();
            JsonResponse::success(
                $result['data'] ?? null,
                (int)($result['status'] ?? 200),
                $result['meta'] ?? []
            );
        } catch (ValidationException $exception) {
            JsonResponse::error($exception->getMessage(), 422);
        } catch (UnauthorizedException $exception) {
            JsonResponse::error($exception->getMessage(), 401);
        } catch (ForbiddenException $exception) {
            JsonResponse::error($exception->getMessage(), 403);
        } catch (NotFoundException $exception) {
            JsonResponse::error($exception->getMessage(), 404);
        } catch (\Throwable) {
            JsonResponse::error('An unexpected server error occurred.', 500);
        }
    }
}
