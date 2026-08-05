<?php

namespace App\Providers;

use App\Events\PurchaseRegistered;
use App\Events\SaleCancelled;
use App\Events\SaleRegistered;
use App\Listeners\RecordStockMovement;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
    }
}
