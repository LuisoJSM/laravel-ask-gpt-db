<?php

namespace App\Providers;

use App\Services\DatabaseAssistant;
use Illuminate\Support\ServiceProvider;


class DatabaseAssistantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DatabaseAssistant::class, function ($app) {
            return new DatabaseAssistant();
        });
    }





    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
