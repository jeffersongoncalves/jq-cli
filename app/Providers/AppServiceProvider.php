<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use JeffersonGoncalves\LaravelZero\SelfUpdate\PharUpdater;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PharUpdater::class, fn () => new PharUpdater(
            githubRepo: 'jeffersongoncalves/jq-cli',
            assetName: 'jq.phar',
            tempPrefix: 'jq_',
            currentVersion: (string) config('app.version', 'unreleased'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
