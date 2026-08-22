<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/me', [ProfileController::class, 'update']);
        Route::put('/me/password', [ProfileController::class, 'updatePassword']);

        Route::apiResource('accounts', AccountController::class);

        Route::apiResource('categories', CategoryController::class)->except(['show']);

        Route::post('/transfers', [TransactionController::class, 'transfer']);
        Route::apiResource('transactions', TransactionController::class);

        Route::apiResource('budgets', BudgetController::class)->except(['show']);

        Route::apiResource('goals', GoalController::class)->except(['show']);
        Route::get('/goals/{goal}/contributions', [GoalController::class, 'contributions']);
        Route::post('/goals/{goal}/contributions', [GoalController::class, 'addContribution']);

        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    });
});
