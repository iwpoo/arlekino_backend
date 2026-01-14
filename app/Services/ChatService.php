<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Events\MessageSent;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ChatService
{
    public function getUserChats(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->chats()
            ->with([
                'users',
                'lastMessage'
            ])
            ->withCount('messages as unread_count')
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('chat_id', 'chats.id')
                    ->latest()
                    ->take(1)
            )
            ->paginate($perPage);
    }

    public function getChatDetails(Chat $chat, User $user): array
    {
        $chat->load(['users', 'messages.user']);

        $otherUser = $chat->is_private ? $chat->users->where('id', '!=', $user->id)->first() : null;
        $isBlocked = $otherUser && $this->checkDoubleBlock($user->id, $otherUser->id);

        Cache::put("user_active_chat:$user->id", $chat->id, now()->addMinutes(30));

        return array_merge($chat->toArray(), ['is_blocked' => $isBlocked]);
    }

    public function createPrivateChat(int $targetUserId, User $currentUser): Chat
    {
        if ($this->checkDoubleBlock($currentUser->id, $targetUserId)) {
            throw ValidationException::withMessages(['chat' => 'Пользователь недоступен.']);
        }

        try {
            return DB::transaction(function () use ($currentUser, $targetUserId) {
                $chat = Chat::where('is_private', true)
                    ->whereHas('users', fn($q) => $q->where('users.id', $currentUser->id))
                    ->whereHas('users', fn($q) => $q->where('users.id', $targetUserId))
                    ->first();

                if (!$chat) {
                    $chat = Chat::create(['is_private' => true]);
                    $chat->users()->attach([$currentUser->id, $targetUserId]);
                }
                return $chat->load('users');
            });
        } catch (QueryException $e) {
            Log::error("Database error during chat creation: " . $e->getMessage(), [
                'users' => [$currentUser->id, $targetUserId]
            ]);
            throw new RuntimeException("Ошибка при создании чата. Попробуйте еще раз.");

        } catch (Throwable $e) {
            Log::critical("Critical chat creation failure: " . $e->getMessage());
            throw $e;
        }
    }

    public function createGroupChat(array $data, User $creator): Chat
    {
        try {
            return DB::transaction(function () use ($data, $creator) {
                $chat = Chat::create([
                    'is_private' => false,
                    'name' => $data['name'],
                    'created_by' => $creator->id
                ]);

                $chat->users()->attach(array_unique(array_merge([$creator->id], $data['user_ids'])));
                return $chat->load('users');
            });
        } catch (QueryException $e) {
            Log::error("Database error during group chat creation: " . $e->getMessage());
            throw new RuntimeException("Не удалось создать чат. Проверьте список пользователей.");
        } catch (Throwable $e) {
            Log::critical("System error in createGroupChat: " . $e->getMessage());
            throw $e;
        }
    }

    public function getChatMessages(Chat $chat, int $perPage = 50): CursorPaginator
    {
        return $chat->messages()
            ->with('user')
            ->latest()
            ->cursorPaginate($perPage);
    }

    public function sendMessage(Chat $chat, array $data, ?object $file, User $user): Message
    {
        $isBlocked = Cache::remember("block_$chat->id", 60, function() use ($chat, $user) {
            return $this->checkDoubleBlock($user->id, $chat->users->where('id', '!=', $user->id)->first()?->id ?? 0);
        });

        if ($chat->is_private && $isBlocked) {
            throw ValidationException::withMessages(['message' => 'Chat blocked']);
        }

        try {
            return DB::transaction(function () use ($chat, $data, $file, $user) {
                $messageData = [
                    'body' => $data['body'] ?? '',
                    'chat_id' => $chat->id,
                    'user_id' => $user->id,
                    'type' => MessageType::TEXT->value
                ];

                if ($file) {
                    $path = $file->store('chats', 'public');
                    $messageData['attachment_url'] = Storage::url($path);
                    $messageData['type'] = str_starts_with($file->getMimeType(), 'image/') ? MessageType::IMAGE->value : MessageType::FILE->value;
                }

                $message = Message::create($messageData);

                DB::afterCommit(function () use ($message) {
                    broadcast(new MessageSent($message->load('user')))->toOthers();
                });

                return $message;
            });
        } catch (Throwable $e) {
            Log::error("Message sending failed: " . $e->getMessage(), [
                'chat_id' => $chat->id,
                'user_id' => $user->id
            ]);

            throw new RuntimeException("Сообщение не отправлено. Попробуйте еще раз.");
        }
    }

    public function updateMessage(Message $message, array $data): Message
    {
        $message->update(['body' => $data['body']]);
        return $message->load('user');
    }

    public function deleteMessage(Message $message): void
    {
        $message->delete();
    }

    private function checkDoubleBlock(int $id1, int $id2): bool
    {
        return UserBlock::where(fn($q) => $q->where('blocker_id', $id1)->where('blocked_id', $id2))
            ->orWhere(fn($q) => $q->where('blocker_id', $id2)->where('blocked_id', $id1))
            ->exists();
    }
}
