<?php

namespace App\Http\Controllers\API\v1;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index(Chat $chat): JsonResponse
    {
        $user = Auth::user();

        if (!$chat->users->contains($user)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $messages = $chat->messages()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request, Chat $chat): JsonResponse
    {
        $user = Auth::user();

        if (!$chat->users->contains($user)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $message = Message::create([
            'body' => $request->body,
            'chat_id' => $chat->id,
            'user_id' => $user->id,
        ]);

        // Загружаем отношение пользователя для ответа
        $message->load('user');

        // Отправляем событие через Reverb
        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message, 201);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        if ($message->user_id !== Auth::id()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $message->update(['body' => $request->body]);

        return response()->json($message->load('user'));
    }

    public function destroy(Message $message): JsonResponse
    {
        if ($message->user_id !== Auth::id()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $message->delete();

        return response()->json(null, 204);
    }
}
