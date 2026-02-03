<?php

namespace App\Services;

use App\Jobs\SendPhoneVerificationCodeJob;
use Exception;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    protected Client $client;
    protected string $serviceSid;

    public function __construct(Client $client, string $serviceSid)
    {
        $this->client = $client;
        $this->serviceSid = $serviceSid;
    }

    public function sendVerificationCodeQueue(string $to): bool
    {
        if (RateLimiter::tooManyAttempts('send-code:'.$to, (int) config('services.twilio.verification_send_attempts_limit', 1))) {
            return false;
        }

        SendPhoneVerificationCodeJob::dispatch($to);

        RateLimiter::hit('send-code:'.$to);
        return true;
    }

    public function sendVerificationCodeSync(string $to): bool
    {
        try {
            $verification = $this->client->verify->v2->services($this->serviceSid)
                ->verifications
                ->create($to, "sms");

            if ($verification->status === 'pending') {
                return true;
            }

            return false;
        } catch (TwilioException $e) {
            $this->logError('sending failed', $to, $e);
            return false;
        } catch (Exception $e) {
            $this->logError('general error during sending', $to, $e);
            return false;
        }
    }

    public function verifyCode(string $to, string $code): bool
    {
        if (RateLimiter::tooManyAttempts('verify-code:'.$to, (int) config('services.twilio.verification_verify_attempts_limit', 5))) {
            return false;
        }

        try {
            $check = $this->client->verify->v2->services($this->serviceSid)
                ->verificationChecks
                ->create(["to" => $to, "code" => $code]);

            if ($check->status === 'approved') {
                RateLimiter::clear('send-code:'.$to);
                RateLimiter::clear('verify-code:'.$to);
                return true;
            }

            RateLimiter::hit('verify-code:'.$to, (int) config('services.twilio.verification_verify_timeout_seconds', 300));
            return false;
        } catch (TwilioException $e) {
            $this->logError('check failed', $to, $e, $code);
            return false;
        }
    }

    private function logError(string $message, string $to, Throwable $e, ?string $code = null): void
    {
        Log::error("Twilio $message: " . $e->getMessage(), [
            'to' => $to,
            'code' => $code,
            'error_code' => $e instanceof TwilioException ? $e->getCode() : 'N/A'
        ]);
    }
}
