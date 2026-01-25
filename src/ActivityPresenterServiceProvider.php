<?php

namespace Deifhelt\ActivityPresenter;

use Deifhelt\ActivityPresenter\Services\RelationResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ActivityPresenterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('activity-presenter')
            ->hasConfigFile('activity-presenter')
            ->hasTranslations();
    }

    public function packageRegistered()
    {
        $this->app->singleton(RelationResolver::class, function ($app) {
            return new RelationResolver(config('activity-presenter', []));
        });

        $this->app->singleton(ActivityLogPresenter::class, function ($app) {
            return new ActivityLogPresenter(
                $app->make(RelationResolver::class),
                $app->make(\Deifhelt\ActivityPresenter\Services\TranslationService::class),
                config('activity-presenter', [])
            );
        });

        $this->app->bind('activity-presenter', ActivityLogPresenter::class);
    }
}
