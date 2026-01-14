<?php

namespace App\Jobs;

use App\Models\OrderReturn;
use App\Notifications\ReturnStatusNotification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoDisposeExpiredReturns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        OrderReturn::where('status', 'condition_bad')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($expiredReturns) {
                foreach ($expiredReturns as $return) {
                    try {
                        $return->update(['status' => 'rejected_by_warehouse']);

                        $return->load(['user', 'order']);

                        $return->user->notify(new ReturnStatusNotification(
                            $return,
                            'rejected_by_warehouse',
                            'Время для принятия решения истекло. Товар был автоматически утилизирован.'
                        ));

                    } catch (Exception $e) {
                        Log::error('Failed to auto-dispose return ID: ' . $return->id, [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });
    }
}
