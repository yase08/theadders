<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategorySubController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\JwtMiddleware;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth');

Route::get('/test', function () {
    return response()->json(['message' => 'success']);
});

// auth start
Route::post('/signup', [AuthController::class, 'signUp']);
Route::post('/login', [AuthController::class, 'login']);
// auth end

Route::middleware('auth:api')->group(function () {
    Route::get('/profile', [AuthController::class, 'getProfile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
});
// category start
Route::post('/category', [CategoryController::class, 'storeCategory']);
Route::get('/categories', [CategoryController::class, 'index']);
// category end

// category sub start
Route::post('/category-sub', [CategorySubController::class, 'storeSubCategory']);
Route::get('/category-subs', [CategorySubController::class, 'index']);
// category sub end

// product start
Route::middleware(JwtMiddleware::class)->group(function () {
    Route::post('/product', [ProductController::class, 'storeProduct']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
});
// product end

// exchange start
Route::middleware('auth:api')->group(function () {
    Route::post('/exchange', [ExchangeController::class, 'requestExchange']);
    Route::put('/approve-exchange/{exchange_id}', [ExchangeController::class, 'approveExchange']);
    Route::put('/decline-exchange/{exchange_id}', [ExchangeController::class, 'declineExchange']);
    Route::get('/exchanges', [ExchangeController::class, 'getUserExchanges']);
    Route::get('/exchange/{exchange_id}', [ExchangeController::class, 'getExchangeById']);
});
// exchange end

// chat start
Route::middleware('auth:api')->group(function () {
    Route::post('/messages/send', [MessageController::class, 'sendMessage']);
    Route::get('/messages/chats', [MessageController::class, 'getChatList']);
    Route::get('/messages/history', [MessageController::class, 'getChatHistory']);
    Route::post('/messages/status', [MessageController::class, 'updateMessageStatus']);
});
// chat end
