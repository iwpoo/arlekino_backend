<?php

namespace App\Policies;

use App\Models\ProductQuestion;
use App\Models\User;

class ProductQuestionPolicy
{
    public function answer(User $user, ProductQuestion $question): bool
    {
        return $user->id === $question->product->user_id && $user->role === 'seller';
    }

    public function delete(User $user, ProductQuestion $question): bool
    {
        return $user->id === $question->user_id;
    }
}
