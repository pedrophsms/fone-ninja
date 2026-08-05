<?php

namespace App\Providers;

use App\Events\PurchaseRegistered;
use App\Events\SaleCancelled;
use App\Events\SaleRegistered;
use App\Listeners\RecordStockMovement;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(PurchaseRegistered::class, [RecordStockMovement::class, 'handlePurchaseRegistered']);
        Event::listen(SaleRegistered::class, [RecordStockMovement::class, 'handleSaleRegistered']);
        Event::listen(SaleCancelled::class, [RecordStockMovement::class, 'handleSaleCancelled']);

        // Temporary financial rate limiter for Task 16 - to be replaced in Task 18
        RateLimiter::for('financial', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
