<?php

namespace App\Jobs;

use App\Models\UserInteraction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class RecordInteractionJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(
        protected int $userId,
        protected int $targetId,
        protected string $targetTable,
        protected string $type,
        protected int $weight
    ) {
        $this->onQueue('analytics');
    }

    public function handle(): void
    {
        UserInteraction::create([
            'user_id' => $this->userId,
            'target_type' => $this->targetTable,
            'target_id' => $this->targetId,
            'interaction_type' => $this->type,
            'weight' => $this->weight,
        ]);

        $modelClass = Relation::getMorphedModel($this->targetTable) ?? null;

        if ($modelClass) {
            $model = $modelClass::find($this->targetId);
            if ($model) {
                SyncEntityToElasticsearch::dispatch($model);
            }
        }
    }
}
