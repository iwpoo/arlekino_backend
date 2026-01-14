<?php

namespace App\Http\Controllers\API\v1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGroupChatRequest;
use App\Http\Requests\CreatePrivateChatRequest;
use App\Models\Chat;
use App\Services\ChatService;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ChatService $chatService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->chatService->getUserChats(auth()->user()));
    }

    public function store(CreatePrivateChatRequest $request): JsonResponse
    {
        try {
            $chat = $this->chatService->createPrivateChat($request->user_id, auth()->user());

            return response()->json($chat, 201);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);

        } catch (Throwable $e) {
            Log::error("Ошибка при создании приватного чата: " . $e->getMessage(), [
                'creator_id' => auth()->id(),
                'target_id'  => $request->user_id
            ]);

            return response()->json([
                'message' => 'Не удалось создать чат. Попробуйте позже.'
            ], 500);
        }
    }

    public function show(Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $chat->setRelation('messages', $this->chatService->getChatMessages($chat));

        return response()->json($chat);
    }

    public function createGroup(CreateGroupChatRequest $request): JsonResponse
    {
        try {
            $chat = $this->chatService->createGroupChat($request->validated(), auth()->user());

            return response()->json($chat, 201);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);

        } catch (Throwable $e) {
            Log::error("Group Chat Creation Failed: " . $e->getMessage(), [
                'user_id' => auth()->id(),
                'payload' => $request->validated()
            ]);

            return response()->json([
                'message' => 'Не удалось создать группу. Пожалуйста, попробуйте позже.'
            ], 500);
        }
    }
}
