# Laravel Activity Presenter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/deifhelt/laravel-activity-presenter.svg?style=flat-square)](https://packagist.org/packages/deifhelt/laravel-activity-presenter)
[![Total Downloads](https://img.shields.io/packagist/dt/deifhelt/laravel-activity-presenter.svg?style=flat-square)](https://packagist.org/packages/deifhelt/laravel-activity-presenter)
[![License](https://img.shields.io/packagist/l/deifhelt/laravel-activity-presenter.svg?style=flat-square)](https://packagist.org/packages/deifhelt/laravel-activity-presenter)

**Laravel Activity Presenter** is a powerful presentation layer for `spatie/laravel-activitylog`. It solves the common challenges of displaying activity logs in your application: resolving relationships efficiently, formatting data consistently, and handling translations.

## Why use this?

When displaying activity logs, you often face these issues:

1.  **N+1 Queries**: Showing "User X updated Project Y" requires loading the User and Project models for every log entry.
2.  **Missing Context**: If "Project Y" is deleted, you still want to show its name in the log history, but the relationship is null.
3.  **Unformatted Data**: You have `user_id: 5` in the log properties, but you want to display "John Doe".
4.  **Inconsistent Presentation**: You find yourself repeating `trans('...')...` logic in every Blade view.

**This package solves all of them.**

## Key Features

-   **🚀 Smart Resolution**: Automatically resolves related models (User, Subject) in a single optimized query to prevent N+1 issues.
-   **💎 Unified DTO**: Transforms raw log data into a consistent `ActivityPresentationDTO` class for easy use in Views and APIs.
-   **🔌 Config-Driven**: Define how specific attributes (like `category_id`) map to models in a simple config file.
-   **🌍 Auto-Localization**: Built-in support for translating events (`created` -> `Creado`), model names (`User` -> `Usuario`), and attributes.
-   **🛡️ Graceful Fallbacks**: If a related model is permanently deleted, it gracefully falls back to historical data (e.g., "Project #123").

## Installation

Install via Composer (Spatie Activitylog is included automatically):

```bash
composer require deifhelt/laravel-activity-presenter
```

Publish the configuration:

```bash
php artisan vendor:publish --provider="Deifhelt\ActivityPresenter\ActivityPresenterServiceProvider"
```

## Quick Usage

### 1. In your Controller

```php
use Deifhelt\ActivityPresenter\Facades\ActivityPresenter;
use Spatie\Activitylog\Models\Activity;

public function index()
{
    // Fetch logs (paginated)
    $activities = Activity::latest()->paginate(20);

    // Present them (loads relations, formats dates, translates events)
    $presented = ActivityPresenter::presentCollection($activities);

    return view('activities.index', [
        'activities' => $presented
    ]);
}
```

### 2. In your Blade View

```blade
@foreach($activities as $activity)
    <div class="activity-item">
        <span class="date">{{ $activity->diff }}</span>

        <!-- "John Doe created Project Alpha" -->
        <p>
            <strong>{{ $activity->user_name }}</strong>
            {{ $activity->event }}
            <strong>{{ $activity->subject_name }}</strong>
        </p>

        <!-- Show changes: "Status: Pending -> Active" -->
        <ul>
            @foreach($activity->new_values as $key => $value)
                <li>
                     {{ $key }}:
                     <span class="text-red-500">{{ $activity->old_values[$key] ?? 'N/A' }}</span>
                     &rarr;
                     <span class="text-green-500">{{ $value }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endforeach
```

## Documentation

-   [Installation & Configuration](docs/installation.md) - Deep dive into setup and config options.
-   [Logging Guide](docs/logging.md) - Best practices for logging model events.
-   [Usage Patterns](docs/usage.md) - Advanced usage in Controllers, Views, and APIs.
-   [Localization](docs/localization.md) - How to translate every aspect of your logs.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
