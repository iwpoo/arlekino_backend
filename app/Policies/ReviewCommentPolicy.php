<?php

namespace App\Policies;

use App\Models\ReviewComment;
use App\Models\User;

class ReviewCommentPolicy
{
    public function delete(User $user, ReviewComment $comment): bool
    {
        return $user->id === $comment->user_id;
    }
}
