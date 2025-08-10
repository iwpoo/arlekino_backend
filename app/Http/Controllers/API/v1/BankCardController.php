<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\BankCard;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BankCardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $cards = Auth::user()->bankCards()->latest()->get();
        return response()->json($cards);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'card_number' => 'required|string|size:16',
            'card_holder' => 'required|string|max:255',
            'expiry_month' => 'required|numeric|between:1,12',
            'expiry_year' => 'required|numeric|min:' . date('y'),
            'cvv' => 'required|numeric|digits:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (Auth::user()->bankCards()->count() >= 5) {
            return response()->json([
                'message' => 'Максимальное количество карт - 5'
            ], 422);
        }

        // Здесь должна быть интеграция с платежной системой
        // Для примера просто эмулируем создание токена
        $token = 'card_' . bin2hex(random_bytes(8));
        $lastFour = substr($request->card_number, -4);

        $card = Auth::user()->bankCards()->create([
            'card_holder' => $request->card_holder,
            'last_four' => $lastFour,
            'brand' => $this->detectCardBrand($request->card_number),
            'token' => $token,
            'is_default' => !Auth::user()->bankCards()->exists(),
        ]);

        if (Auth::user()->bankCards()->count() === 1) {
            Auth::user()->update(['default_card_id' => $card->id]);
        }

        return response()->json($card, 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BankCard $card): JsonResponse
    {
        if ($card->user_id !== Auth::id()) {
            return response()->json(null, 403);
        }

        $card->delete();

        if (Auth::user()->default_card_id === $card->id) {
            Auth::user()->update([
                'default_card_id' => Auth::user()->bankCards()->first()?->id
            ]);
        }

        return response()->json(null, 204);
    }

    public function setDefault(BankCard $card): JsonResponse
    {
        if ($card->user_id !== Auth::id()) {
            return response()->json(null, 403);
        }

        Auth::user()->update(['default_card_id' => $card->id]);

        return response()->json($card);
    }

    private function detectCardBrand($number): string
    {
        $firstDigit = substr($number, 0, 1);

        return match($firstDigit) {
            '4' => 'Visa',
            '5' => 'Mastercard',
            '3' => 'American Express',
            '6' => 'Discover',
            default => 'Unknown'
        };
    }
}
