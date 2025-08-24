<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $chats = $user->chats()
            ->with(['users' => function ($query) use ($user) {
                $query->where('users.id', '!=', $user->id);
            }, 'lastMessage'])
            ->orderByDesc(
                Chat::select('created_at')
                    ->from('messages')
                    ->whereColumn('messages.chat_id', 'chats.id')
                    ->latest()
                    ->limit(1)
            )
            ->get();

        return response()->json($chats);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = Auth::user();
        $otherUser = User::find($request->user_id);

        // Проверяем, существует ли уже чат между пользователями
        $existingChat = $user->chats()
            ->whereHas('users', function ($query) use ($otherUser) {
                $query->where('users.id', $otherUser->id);
            })
            ->first();

        if ($existingChat) {
            return response()->json($existingChat->load('users', 'lastMessage'));
        }

        // Создаем новый чат
        $chat = Chat::create(['is_private' => true]);
        $chat->users()->attach([$user->id, $otherUser->id]);

        return response()->json($chat->load('users', 'lastMessage'), 201);
    }

    public function show(Chat $chat): JsonResponse
    {
        $user = Auth::user();

        // Проверяем, принадлежит ли чат пользователю
        if (!$chat->users->contains($user)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $chat->load(['users' => function ($query) use ($user) {
            $query->where('users.id', '!=', $user->id);
        }, 'messages.user']);

        return response()->json($chat);
    }
}
