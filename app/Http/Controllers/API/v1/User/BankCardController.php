<?php

namespace App\Http\Controllers\API\v1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\BankCardCreateRequest;
use App\Models\BankCard;
use App\Services\BankCardService;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BankCardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BankCardService $cardService
    ) {}

    public function index(): JsonResponse
    {
        $cards = Auth::user()->bankCards()
            ->latest()
            ->get()
            ->makeHidden(['token']);

        return response()->json($cards);
    }

    public function store(BankCardCreateRequest $request): JsonResponse
    {
        try {
            $card = $this->cardService->addCard(Auth::user(), $request->validated());

            return response()->json($card->makeHidden(['token']), 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(BankCard $card): JsonResponse
    {
        $this->authorize('delete', $card);

        $this->cardService->deleteCard($card, Auth::user());

        return response()->json(null, 204);
    }

    public function setDefault(BankCard $card): JsonResponse
    {
        $this->authorize('update', $card);

        Auth::user()->update(['default_card_id' => $card->id]);

        return response()->json($card->makeHidden(['token']));
    }
}
