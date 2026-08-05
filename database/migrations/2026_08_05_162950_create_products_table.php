<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('sale_price_cents');
            $table->bigInteger('average_cost_cents')->default(0);
            $table->unsignedBigInteger('current_stock')->default(0);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_sale_price_positive CHECK (sale_price_cents > 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_current_stock_non_negative CHECK (current_stock >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
