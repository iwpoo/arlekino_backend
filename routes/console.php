<?php

use App\Jobs\PrecomputeSellerAnalytics;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\AutoDisposeExpiredReturns;
use App\Models\User;

Schedule::command('currency:refresh')->dailyAt('00:30');

Schedule::job(new AutoDisposeExpiredReturns)->hourly();

Schedule::call(function () {
    User::where('role', 'seller')->chunk(100, function ($sellers) {
        foreach ($sellers as $seller) {
            PrecomputeSellerAnalytics::dispatch($seller, 'month');
        }
    });
})->dailyAt('02:00');
