<?php

namespace App\Providers;

use App\Events\OrderStatusUpdated;
use App\Helpers\MediaUploader;
use App\Listeners\SendOrderStatusNotification;
use App\Services\ClientProfileService;
use App\Services\Contracts\ProfileServiceInterface;
use App\Services\SellerProfileService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProfileServiceInterface::class, function ($app) {
            $user = $app->make('request')->route('user');

            if ($user->isClient()) {
                return new ClientProfileService($app->make(MediaUploader::class));
            } else {
                return new SellerProfileService($app->make(MediaUploader::class));
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            OrderStatusUpdated::class,
            SendOrderStatusNotification::class,
        );
    }
}
