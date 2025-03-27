<?php

namespace App\Providers;

use App\Models\Translation;
use App\Repositories\Interfaces\TranslationRepositoryInterface;
use App\Repositories\TranslationRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the repository bindings
        $this->app->bind(TranslationRepositoryInterface::class, function ($app) {
            return new TranslationRepository(new Translation());
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
