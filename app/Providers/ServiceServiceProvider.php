<?php

namespace App\Providers;

use App\Repositories\Interfaces\TranslationRepositoryInterface;
use App\Services\ExportService;
use App\Services\TranslationService;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register translation service
        $this->app->singleton(TranslationService::class, function ($app) {
            return new TranslationService(
                $app->make(TranslationRepositoryInterface::class)
            );
        });
        
        // Register export service
        $this->app->singleton(ExportService::class, function ($app) {
            return new ExportService(
                $app->make(TranslationService::class)
            );
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
