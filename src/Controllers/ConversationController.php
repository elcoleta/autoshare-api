<?php

namespace App\Controllers;

use App\Services\ConversationService;

class ConversationController extends ApiController
{
    public function index(array $vars = []): void
    {
        $this->respond(fn () => (new ConversationService())->paginate($this->request()));
    }

    public function store(array $vars = []): void
    {
        $this->respond(fn () => [
            'status' => 201,
            'data' => (new ConversationService())->create($this->request()->all()),
        ]);
    }

    public function messages(array $vars = []): void
    {
        $this->respond(fn () => (new ConversationService())->messages((int)($vars['id'] ?? 0), $this->request()));
    }

    public function send(array $vars = []): void
    {
        $this->respond(fn () => [
            'status' => 201,
            'data' => (new ConversationService())->send((int)($vars['id'] ?? 0), $this->request()->all()),
        ]);
    }
}
