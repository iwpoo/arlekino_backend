<?php

namespace App\Http\Controllers\API\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmailVerificationRequest;
use App\Http\Requests\SendEmailVerificationRequest;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationController extends Controller
{
    public function __construct(
        protected EmailVerificationService $emailVerificationService
    ) {}

    public function sendVerificationCode(SendEmailVerificationRequest $request): JsonResponse
    {
        $this->limitAttempts('send-reg:' . $request->email, 1, 60);

        $this->emailVerificationService->sendRegistrationCode($request->email);

        return response()->json(['message' => 'Код подтверждения отправлен.']);
    }

    public function verifyCode(EmailVerificationRequest $request): JsonResponse
    {
        $this->limitAttempts('verify-reg:' . $request->email, 5, 300);

        $this->emailVerificationService->verifyRegistrationCode($request->email, $request->code);

        RateLimiter::clear('verify-reg:' . $request->email);

        return response()->json(['message' => 'Email успешно подтвержден.']);
    }

    public function resendEmailAttachmentCode(SendEmailVerificationRequest $request): JsonResponse
    {
        $this->limitAttempts('send-attach:' . $request->user()->id, 1, 60);

        $this->emailVerificationService->sendAttachmentCode($request->user(), $request->email);

        return response()->json(['message' => 'Код для привязки email отправлен.']);
    }

    public function verifyEmailAttachmentCode(EmailVerificationRequest $request): JsonResponse
    {
        $this->limitAttempts('verify-attach:' . $request->user()->id, 5, 300);

        $user = $this->emailVerificationService->verifyAndAttachEmail(
            $request->user(),
            $request->email,
            $request->code
        );

        RateLimiter::clear('verify-attach:' . $request->user()->id);

        return response()->json([
            'message' => 'Email успешно обновлен.',
            'user' => $user
        ]);
    }

    private function limitAttempts(string $key, int $maxAttempts, int $decaySeconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            abort(429, "Слишком много попыток. Подождите $seconds сек.");
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
