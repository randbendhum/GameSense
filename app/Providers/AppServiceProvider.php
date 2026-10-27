<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Previne N+1 queries em desenvolvimento e protege a RAM da Oracle E2.1.Micro
        Model::preventLazyLoading(! $this->app->isProduction());

        // Força HTTPS automaticamente quando acessado via subdomínio em produção
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}

