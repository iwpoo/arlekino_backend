<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class SessionController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = auth()->id();

        $sessions = Session::where('user_id', $userId)
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(function (Session $session) {
                return [
                    'id'             => $session->id,
                    'ip'             => $session->ip_address,
                    'agent'          => $session->user_agent,
                    'last_activity'  => Carbon::createFromTimestamp($session->last_activity)
                        ->toDateTimeString(),
                ];
            });

        return response()->json($sessions);
    }

    public function destroy(string|int $id): JsonResponse
    {
        Session::where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json(['message' => 'Сессия удалена']);
    }
}
