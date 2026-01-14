<?php

namespace App\Services;

use App\Models\BankCard;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;


// TODO: Реализовать реальную платежную систему
class BankCardService
{
    public function addCard(User $user, array $data): BankCard
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $userCards = $user->bankCards;

                if ($userCards->count() >= 5) {
                    throw new DomainException('Максимальное количество карт — 5');
                }

                $isFirst = $userCards->isEmpty();

                $card = $user->bankCards()->create([
                    'card_holder' => $data['card_holder'],
                    'last_four' => substr($data['card_number'], -4),
                    'brand' => $this->detectBrand($data['card_number']),
                    'token' => 'card_' . bin2hex(random_bytes(8)), // Эмуляция токена
                    'is_default' => $isFirst,
                ]);

                if ($isFirst) {
                    $user->update(['default_card_id' => $card->id]);
                }

                return $card;
            });
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("Ошибка при привязке карты: " . $e->getMessage(), [
                'user_id' => $user->id,
                'brand'   => $this->detectBrand($data['card_number'])
            ]);

            throw new RuntimeException("Не удалось сохранить карту. Попробуйте позже.");
        }
    }

    public function deleteCard(BankCard $card, User $user): void
    {
        try {
            DB::transaction(function () use ($card, $user) {
                $card->delete();

                if ($user->default_card_id === $card->id) {
                    $newDefault = $user->bankCards()->first();
                    $user->update(['default_card_id' => $newDefault?->id]);
                }
            });
        } catch (Throwable $e) {
            Log::error("Ошибка при удалении карты [Card ID: $card->id]: " . $e->getMessage());

            throw new RuntimeException("Ошибка при удалении карты из системы");
        }
    }

    private function detectBrand(string $number): string
    {
        return match($number[0]) {
            '4' => 'Visa',
            '5' => 'Mastercard',
            '2' => 'Mir',
            '3' => 'American Express',
            default => 'Unknown'
        };
    }
}
