<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\PurchasesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/registro', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/produtos', [ProductsController::class, 'index']);
    Route::post('/produtos', [ProductsController::class, 'store']);
    Route::get('/compras', [PurchasesController::class, 'index']);
    Route::post('/compras', [PurchasesController::class, 'store'])->middleware(['idempotent', 'throttle:financial']);
});
