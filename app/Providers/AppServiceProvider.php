<?php

namespace App\Providers;

use App\Events\MessageSent;
use App\Events\OrderCreate;
use App\Events\OrderStatusUpdated;
use App\Events\ProductQuestionAnswered;
use App\Events\ProductQuestionCreated;
use App\Events\SellerOrderStatusUpdated;
use App\Events\SocialActivity;
use App\Helpers\MediaUploader;
use App\Listeners\SendMessageNotification;
use App\Listeners\SendOrderCreateNotification;
use App\Listeners\SendOrderStatusNotification;
use App\Listeners\SendProductQuestionAnsweredNotification;
use App\Listeners\SendProductQuestionCreatedNotification;
use App\Listeners\SendSellerOrderStatusNotification;
use App\Listeners\SendSocialActivityNotification;
use App\Services\ClientProfileService;
use App\Services\Contracts\OrderProcessingServiceInterface;
use App\Services\Contracts\ProfileServiceInterface;
use App\Services\Contracts\ReturnsProcessingServiceInterface;
use App\Services\OrderService;
use App\Services\RecommendationService;
use App\Services\ReturnsProcessingService;
use App\Services\SellerProfileService;
use App\Services\TwilioService;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Twilio\Rest\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProfileServiceInterface::class, function ($app) {
            $user = $app->make('request')->route('user');

            if ($user && $user->isClient()) {
                return new ClientProfileService($app->make(MediaUploader::class));
            } else {
                return new SellerProfileService($app->make(MediaUploader::class));
            }
        });

        $this->app->singleton(OrderProcessingServiceInterface::class, function ($app) {
            return new OrderService($app->make('App\Services\CurrencyConverter'), $app->make('App\Services\PriceCalculatorService'));
        });

        $this->app->singleton(ReturnsProcessingServiceInterface::class, function ($app) {
            return new ReturnsProcessingService($app->make('App\Services\CurrencyConverter'));
        });

        $this->app->singleton(TwilioService::class, function () {
            $config = config('services.twilio');

            return new TwilioService(
                new Client($config['sid'], $config['token']),
                $config['service_sid']
            );
        });

        $this->app->singleton(\Elastic\Elasticsearch\Client::class, function () {
            return ClientBuilder::create()
                ->setHosts([config('services.elasticsearch.host')])
                ->setRetries(2)
                ->build();
        });

        $this->app->singleton(RecommendationService::class, function ($app) {
            return new RecommendationService($app->make(\Elastic\Elasticsearch\Client::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            OrderStatusUpdated::class,
            SendOrderStatusNotification::class
        );
        Event::listen(
            OrderCreate::class,
            SendOrderCreateNotification::class
        );
        Event::listen(
            SellerOrderStatusUpdated::class,
            SendSellerOrderStatusNotification::class
        );
        Event::listen(
            SocialActivity::class,
            SendSocialActivityNotification::class
        );
        Event::listen(
            MessageSent::class,
            SendMessageNotification::class
        );
        Event::listen(
            ProductQuestionAnswered::class,
            SendProductQuestionAnsweredNotification::class
        );
        Event::listen(
            ProductQuestionCreated::class,
            SendProductQuestionCreatedNotification::class
        );
    }
}
