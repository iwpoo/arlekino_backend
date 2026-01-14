<?php

namespace App\Http\Controllers\API\v1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Chat;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ChatService $chatService
    ) {}

    public function index(Chat $chat, Request $request): JsonResponse
    {
        $this->authorize('view', $chat);

        $perPage = $request->integer('per_page', 50);

        $messages = $this->chatService->getChatMessages($chat, $perPage);
        return response()->json($messages);
    }

    public function store(SendMessageRequest $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $message = $this->chatService->sendMessage(
            $chat,
            $request->validated(),
            $request->file('file'),
            auth()->user()
        );

        return response()->json($message, 201);
    }

    public function update(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('update', $message);

        $updatedMessage = $this->chatService->updateMessage($message, $request->validated());
        return response()->json($updatedMessage);
    }

    public function destroy(Message $message): JsonResponse
    {
        $this->authorize('delete', $message);

        $this->chatService->deleteMessage($message);
        return response()->json(null, 204);
    }
}
