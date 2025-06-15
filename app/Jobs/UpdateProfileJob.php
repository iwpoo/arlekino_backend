<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Contracts\ProfileServiceInterface;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class UpdateProfileJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected User $user,
        protected array $data
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ProfileServiceInterface $service): void
    {
        try {
            $service->update($this->data);
        } catch (Exception $e) {
            Log::error('Profile update error: '.$e->getMessage());
            $this->fail($e);
        }
    }
}
