<?php

namespace App\Jobs;

use App\Models\OrderReturn;
use App\Notifications\ReturnStatusNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Log;
use Throwable;

class ProcessRefundPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 60;

    public int $tries = 5;

    public function __construct(protected OrderReturn $return) {
        $this->onQueue('high');
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $return = OrderReturn::where('id', $this->return->id)
            ->where('status', 'refund_initiated')
            ->lockForUpdate()
            ->first();

        if (!$return) {
            Log::warning("Refund already processed or status changed for return ID: {$this->return->id}");
            return;
        }

        try {
            // TODO: Implement actual payment processing logic here

            DB::transaction(function () use ($return) {
                $return->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                $return->load(['order', 'user', 'seller']);

                $return->user->notify(new ReturnStatusNotification($return, 'completed', 'Деньги возвращены'));
                $return->seller->notify(new ReturnStatusNotification($return, 'completed', 'Возврат завершен'));
            });

            Log::info('Refund successfully completed for return ID: ' . $return->id);

        } catch (Throwable $e) {
            Log::critical('Payment Gateway Error: ' . $e->getMessage(), [
                'return_id' => $return->id
            ]);

            throw $e;
        }
    }
}
