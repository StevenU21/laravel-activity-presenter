<?php

namespace Deifhelt\ActivityPresenter\Tests;

use Deifhelt\ActivityPresenter\ActivityPresenterServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'Deifhelt\\ActivityPresenter\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            ActivitylogServiceProvider::class,
            ActivityPresenterServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Setup activity log migration
        $migrationFile = __DIR__ . '/../vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub';

        if (file_exists($migrationFile)) {
            if (!class_exists('CreateActivityLogTable')) {
                include $migrationFile;
            }
            (new \CreateActivityLogTable)->up();

            // Fix for potential missing event column in older migrations or specific environments
            if (!\Illuminate\Support\Facades\Schema::hasColumn('activity_log', 'event')) {
                \Illuminate\Support\Facades\Schema::table('activity_log', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->string('event')->nullable();
                });
            }
        }

    }
}
