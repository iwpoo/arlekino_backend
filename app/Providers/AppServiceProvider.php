<?php

namespace App\Providers;

use App\Services\ClientProfileService;
use App\Services\Contracts\ProfileServiceInterface;
use App\Services\SellerProfileService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProfileServiceInterface::class, function ($app) {
            $user = $app->make('request')->route('user');

            if ($user->isClient()) {
                return new ClientProfileService();
            } else {
                return new SellerProfileService();
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
