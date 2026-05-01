<?php

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Framework\ApiAuth;
use App\Framework\Request;
use App\Repositories\ConversationRepository;
use App\Repositories\UserRepository;

class ConversationService
{
    private ConversationRepository $conversations;
    private UserRepository $users;

    public function __construct()
    {
        $this->conversations = new ConversationRepository();
        $this->users = new UserRepository();
    }

    public function paginate(Request $request): array
    {
        $user = ApiAuth::requireUser();
        $result = $this->conversations->paginateForUser((int)$user['id'], [
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 8),
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
        $recipientId = (int)($payload['recipient_id'] ?? 0);
        $message = trim((string)($payload['message'] ?? ''));

        if ($recipientId <= 0) {
            throw new ValidationException('Choose a valid recipient.');
        }

        if ($recipientId === (int)$user['id']) {
            throw new ValidationException('You cannot start a conversation with yourself.');
        }

        if ($this->users->findPublicById($recipientId) === null) {
            throw new NotFoundException('Recipient not found.');
        }

        $conversation = $this->conversations->findBetweenUsers((int)$user['id'], $recipientId);
        if ($conversation === null) {
            $conversation = $this->conversations->create((int)$user['id'], $recipientId);
        }

        if ($message !== '') {
            $this->conversations->addMessage((int)$conversation['id'], (int)$user['id'], $message);
        }

        return [
            'conversation_id' => (int)$conversation['id'],
        ];
    }

    public function messages(int $conversationId, Request $request): array
    {
        $user = ApiAuth::requireUser();
        $conversation = $this->assertParticipant($conversationId, (int)$user['id']);
        $result = $this->conversations->paginateMessages($conversationId, [
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ]);

        return [
            'data' => [
                'conversation' => $conversation,
                'messages' => $result['items'],
            ],
            'meta' => [
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'total_pages' => (int)max(1, ceil($result['total'] / $result['per_page'])),
            ],
        ];
    }

    public function send(int $conversationId, array $payload): array
    {
        $user = ApiAuth::requireUser();
        $this->assertParticipant($conversationId, (int)$user['id']);

        $body = trim((string)($payload['body'] ?? ''));
        if ($body === '') {
            throw new ValidationException('Write a message before sending.');
        }

        return $this->conversations->addMessage($conversationId, (int)$user['id'], $body);
    }

    private function assertParticipant(int $conversationId, int $userId): array
    {
        if ($conversationId <= 0) {
            throw new ValidationException('Invalid conversation id.');
        }

        $conversation = $this->conversations->findById($conversationId);
        if ($conversation === null) {
            throw new NotFoundException('Conversation not found.');
        }

        $participants = [(int)$conversation['user_one_id'], (int)$conversation['user_two_id']];
        if (!in_array($userId, $participants, true)) {
            throw new ForbiddenException('You cannot access this conversation.');
        }

        return $conversation;
    }
}
