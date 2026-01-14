<?php

namespace App\Providers;

use App\Models\BankCard;
use App\Models\CartItem;
use App\Models\Chat;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\OrderReturn;
use App\Models\ReviewComment;
use App\Models\SellerOrder;
use App\Models\User;
use App\Policies\BankCardPolicy;
use App\Policies\CartItemPolicy;
use App\Policies\ChatPolicy;
use App\Policies\CommentPolicy;
use App\Policies\MessagePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PostPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductQuestionPolicy;
use App\Policies\ReviewCommentPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\ReturnPolicy;
use App\Policies\SellerOrderPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Post::class => PostPolicy::class,
        Product::class => ProductPolicy::class,
        Review::class => ReviewPolicy::class,
        OrderReturn::class => ReturnPolicy::class,
        Comment::class => CommentPolicy::class,
        CartItem::class => CartItemPolicy::class,
        Order::class => OrderPolicy::class,
        Chat::class => ChatPolicy::class,
        Message::class => MessagePolicy::class,
        ReviewComment::class => ReviewCommentPolicy::class,
        SellerOrder::class => SellerOrderPolicy::class,
        ProductQuestion::class => ProductQuestionPolicy::class,
        User::class => UserPolicy::class,
        BankCard::class => BankCardPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
