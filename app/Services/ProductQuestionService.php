<?php

namespace App\Services;

use App\Events\ProductQuestionAnswered;
use App\Events\ProductQuestionCreated;
use App\Jobs\UpdateQuestionCounterJob;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProductQuestionService
{
    public function getQuestions(Product $product, ?User $user): LengthAwarePaginator
    {
        $query = $product->questions()
            ->with(['user', 'answeredBy'])
            ->latest();

        if ($user) {
            $query->withExists(['helpfulUsers as is_helpful' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);
        }

        return $query->paginate(10);
    }

    public function createQuestion(Product $product, User $user, array $data): ProductQuestion
    {
        try {
            return DB::transaction(function () use ($product, $user, $data) {
                $question = $product->questions()->create([
                    'user_id' => $user->id,
                    'question' => $data['question'],
                ]);

                $product->increment('questions_count');

                DB::afterCommit(function () use ($question) {
                    event(new ProductQuestionCreated($question));
                });

                return $question->load('user');
            });
        } catch (Throwable $e) {
            Log::error("Ошибка создания вопроса к товару $product->id: " . $e->getMessage(), [
                'user_id' => $user->id,
                'data'    => $data
            ]);

            throw new RuntimeException("Не удалось сохранить ваш вопрос. Попробуйте позже.");
        }
    }

    public function updateQuestionAnswer(ProductQuestion $question, User $user, array $data): ProductQuestion
    {
        $isFirstAnswer = is_null($question->answer);

        $question->update([
            'answer' => $data['answer'],
            'answered_by' => $user->id,
            'answered_at' => $question->answered_at ?? now(),
        ]);

        if ($isFirstAnswer) {
            event(new ProductQuestionAnswered($question));
        }

        return $question->load(['user', 'answeredBy']);
    }

    public function deleteQuestion(ProductQuestion $question): void
    {
        try {
            DB::transaction(function () use ($question) {
                $product = $question->product;
                $question->delete();
                $product->decrement('questions_count');
            });
        } catch (Throwable $e) {
            Log::error("Ошибка удаления вопроса [ID: $question->id]: " . $e->getMessage());

            throw new RuntimeException("Не удалось удалить вопрос.");
        }
    }

    public function toggleHelpful(ProductQuestion $question, User $user): array
    {
        $changes = $question->helpfulUsers()->toggle($user->id);

        $isAttached = count($changes['attached']) > 0;
        $type = $isAttached ? 'increment' : 'decrement';

        UpdateQuestionCounterJob::dispatch($question->id, 'helpful_count', $type);

        return [
            'helpful_count' => $isAttached ? $question->helpful_count + 1 : $question->helpful_count - 1,
            'is_helpful' => $isAttached
        ];
    }
}
