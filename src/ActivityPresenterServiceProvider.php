<?php

namespace Deifhelt\ActivityPresenter;

use Deifhelt\ActivityPresenter\Services\RelationResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ActivityPresenterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('activity-presenter') // matches config filename
            ->hasConfigFile('activity-presenter');
    }

    public function packageRegistered()
    {
        $this->app->singleton(RelationResolver::class, function ($app) {
            return new RelationResolver(config('activity-presenter', []));
        });

        $this->app->singleton(ActivityLogPresenter::class, function ($app) {
            return new ActivityLogPresenter(
                $app->make(RelationResolver::class),
                config('activity-presenter', [])
            );
        });

        $this->app->bind('activity-presenter', ActivityLogPresenter::class);
    }
}
