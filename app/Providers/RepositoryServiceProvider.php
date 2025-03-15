<?php

namespace App\Providers;

use App\Interfaces\ExchangeInterface;
use App\Interfaces\WishlistInterface;
use App\Interfaces\RatingInterface;
use App\Interfaces\ProductCategoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserFollowInterface;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\WishlistRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserFollowRepository;
use App\Repositories\RatingRepository;
use App\Repositories\ExchangeRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        $this->app->bind(ProductCategoryInterface::class, ProductCategoryRepository::class);

        $this->app->bind(ExchangeInterface::class, ExchangeRepository::class);

        $this->app->bind(WishlistInterface::class, WishlistRepository::class);

        $this->app->bind(UserFollowInterface::class, UserFollowRepository::class);

        $this->app->bind(RatingInterface::class, RatingRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
