<?php

namespace App\Jobs;

use App\Services\TwilioService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPhoneVerificationCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120];

    public function __construct(protected string $to) {
        $this->onQueue('high');
    }

    /**
     * @throws Exception
     */
    public function handle(TwilioService $twilio): void
    {
        try {
            Log::info("Attempting to send verification code to: $this->to");

            $twilio->sendVerificationCodeSync($this->to);

        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'is not a mobile number')) {
                Log::error("Twilio: Invalid phone number {$this->to}");
                $this->fail($e);
                return;
            }

            Log::warning("Failed to send SMS to {$this->to}, retrying... Error: " . $e->getMessage());
            throw $e;
        }
    }
}
