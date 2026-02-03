<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrackUserDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        protected User $user,
        protected string $ip,
        protected string $userAgent
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $user = User::where('id', $this->user->id)->lockForUpdate()->first();

            if (!$user) return;

            $devices = collect($user->authorized_devices ?? []);

            $newDevice = [
                'ip'         => $this->ip,
                'ua'         => $this->userAgent,
                'last_login' => now()->toDateTimeString()
            ];

            $filtered = $devices->reject(function ($d) {
                return ($d['ip'] ?? '') === $this->ip && ($d['ua'] ?? '') === $this->userAgent;
            });

            $user->update([
                'authorized_devices' => $filtered->prepend($newDevice)->take(10)->values()->toArray()
            ]);
        });
    }
}
