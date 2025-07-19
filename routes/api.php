<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategorySubController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\UserFollowController;
use App\Http\Controllers\WishlistController;
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
Route::post('/sync-firebase-user', [AuthController::class, 'syncFirebaseUser']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
// auth end

Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

Route::middleware(JwtMiddleware::class)->group(function () {
    Route::get('/profile', [AuthController::class, 'getProfile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::get('/user/{userId}', [AuthController::class, 'getUserById']);
    Route::post('/profile/additional-info', [AuthController::class, 'updateAdditionalProfileInfo']);
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
    Route::get('/my-products', [ProductController::class, 'myProducts']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/my-trade-history', [ProductController::class, 'tradeHistory']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}', [ProductController::class, 'update']);
});
// product end

// exchange start
Route::middleware(JwtMiddleware::class)->group(function () {
    Route::post('/exchange', [ExchangeController::class, 'requestExchange']);
    Route::put('/approve-exchange/{exchange_id}', [ExchangeController::class, 'approveExchange']);
    Route::put('/decline-exchange/{exchange_id}', [ExchangeController::class, 'declineExchange']);
    Route::get('/exchanges', [ExchangeController::class, 'getUserExchanges']);
    Route::get('/exchanges/incoming', [ExchangeController::class, 'getIncomingExchanges']);
    Route::get('/exchanges/outgoing', [ExchangeController::class, 'getOutgoingExchanges']);
    Route::get('/exchange/{exchange_id}', [ExchangeController::class, 'getExchangeById']);
    Route::post('/exchange/{exchangeId}/complete', [ExchangeController::class, 'completeExchange']);
    Route::post('/exchange/{exchangeId}/cancel', [ExchangeController::class, 'cancelExchange']);
    Route::get('/exchange/product/{productId}', [ExchangeController::class, 'getProductExchangeRequests']);
});
// exchange end

// chat start
Route::middleware(JwtMiddleware::class)->group(function () {
    Route::post('/messages/send', [MessageController::class, 'sendMessage']);
    Route::get('/messages/chats', [MessageController::class, 'getChatList']);
    Route::get('/messages/history', [MessageController::class, 'getChatHistory']);
    Route::post('/messages/status', [MessageController::class, 'updateMessageStatus']);
    Route::post('/messages/client-status', [MessageController::class, 'updateClientStatus']);
});
// chat end

Route::middleware(JwtMiddleware::class)->group(function () {
    // User Follow routes
    Route::post('/users/{userId}/follow', [UserFollowController::class, 'follow']);
    Route::delete('/users/{userId}/unfollow', [UserFollowController::class, 'unfollow']);
    Route::get('/users/{userId}/followers', [UserFollowController::class, 'followers']);
    Route::get('/users/{userId}/following', [UserFollowController::class, 'following']);
    Route::get('/users/{userId}/follow-status', [UserFollowController::class, 'checkFollow']);
});

Route::middleware(JwtMiddleware::class)->group(function () {
    // Wishlist routes
    Route::post('/wishlist', [WishlistController::class, 'addToWishlist']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'removeFromWishlist']);
    Route::get('/wishlist', [WishlistController::class, 'getUserWishlist']);
    Route::get('/wishlist/check/{productId}', [WishlistController::class, 'checkWishlist']);
    Route::get('/wishlist/my-product-wishlisters', [WishlistController::class, 'getUsersWhoWishlistedMyProducts']);
});

Route::middleware(JwtMiddleware::class)->group(function () {
    // Rating routes
    Route::post('/rate-exchange', [RatingController::class, 'rateExchangeProduct']);
    Route::get('/product/{productId}/ratings', [RatingController::class, 'getProductRatings']);
    Route::get('/user/{userId}/ratings', [RatingController::class, 'getUserRatings']);
});

Route::middleware(JwtMiddleware::class)->group(function () {
    // Rating routes
    Route::post('/rate-user', [RatingController::class, 'rateExchangeUser']);
    Route::get('/user/{userId}/ratings/received', [RatingController::class, 'getUserRatings']);
    Route::get('/user/{userId}/ratings/given', [RatingController::class, 'getGivenRatings']);
});
