<?php

namespace Database\Seeders;

use App\Actions\Purchase\RegisterPurchaseAction;
use App\Actions\Sale\RegisterSaleAction;
use App\DataTransferObjects\RegisterPurchaseData;
use App\DataTransferObjects\RegisterSaleData;
use App\Models\Product;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@fone-ninja.test'],
            ['name' => 'Usuário Demo', 'password' => bcrypt('password123')],
        );

        $headphones = Product::create(['name' => 'Fone Bluetooth', 'sale_price_cents' => Money::fromDecimalString('99.90'), 'average_cost_cents' => Money::zero(), 'current_stock' => 0]);
        $charger = Product::create(['name' => 'Carregador USB-C', 'sale_price_cents' => Money::fromDecimalString('49.90'), 'average_cost_cents' => Money::zero(), 'current_stock' => 0]);
        $cable = Product::create(['name' => 'Cabo USB-C 1m', 'sale_price_cents' => Money::fromDecimalString('19.90'), 'average_cost_cents' => Money::zero(), 'current_stock' => 0]);

        app(RegisterPurchaseAction::class)->execute(
            RegisterPurchaseData::fromValidated([
                'fornecedor' => 'Fornecedor Demo',
                'produtos' => [
                    ['id' => $headphones->id, 'quantidade' => 50, 'preco_unitario' => '60.00'],
                    ['id' => $charger->id, 'quantidade' => 100, 'preco_unitario' => '25.00'],
                    ['id' => $cable->id, 'quantidade' => 200, 'preco_unitario' => '8.00'],
                ],
            ]),
            $user->id,
        );

        app(RegisterSaleAction::class)->execute(
            RegisterSaleData::fromValidated([
                'cliente' => 'Cliente Demo',
                'produtos' => [
                    ['id' => $headphones->id, 'quantidade' => 5, 'preco_unitario' => '99.90'],
                    ['id' => $cable->id, 'quantidade' => 10, 'preco_unitario' => '19.90'],
                ],
            ]),
            $user->id,
        );
    }
}
