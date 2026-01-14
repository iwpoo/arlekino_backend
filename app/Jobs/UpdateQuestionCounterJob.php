<?php

namespace App\Jobs;

use App\Models\ProductQuestion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class UpdateQuestionCounterJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(
        protected int $questionId,
        protected string $column,
        protected string $type = 'increment'
    ) {
        $this->onQueue('analytics');
    }

    public function handle(): void
    {
        $question = ProductQuestion::find($this->questionId);
        if (!$question) return;

        $this->type === 'increment'
            ? $question->increment($this->column)
            : $question->decrement($this->column);
    }
}
