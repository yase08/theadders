<?php

namespace App\Providers;

use App\Interfaces\ExchangeInterface;
use App\Interfaces\ProductCategoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\UserRepository;
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

        $this->app->bind(ExchangeInterface::class, \App\Repositories\ExchangeRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
