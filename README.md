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

- **🚀 Smart Resolution**: Automatically resolves related models (User, Subject) in a single optimized query to prevent N+1 issues.
- **💎 Object-Oriented**: Provides rich objects (`LogEntry`, `AttributeChange`) instead of flat strings, giving you full control in your View.
- **🔌 Config-Driven**: Define how specific attributes (like `category_id`) map to models in a simple config file.
- **🌍 Auto-Localization**: Built-in support for translating events (`created` -> `Creado`), model names (`User` -> `Usuario`), and attributes.
- **🛡️ Agnostic & Flexible**: Does not force date formats or string styles. You get the raw `Carbon` objects and Models to format however you like.

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
@foreach($activities as $log)
    <div class="activity-item">
        <!-- Full Control over Date Format -->
        <span class="date">{{ $log->activity->created_at->diffForHumans() }}</span>

        <!-- "John Doe created Project Alpha" -->
        <p>
            @if($log->causer)
                <a href="{{ route('users.show', $log->causer) }}">
                    <strong>{{ $log->getCauserLabel() }}</strong>
                </a>
            @else
                System
            @endif

            {{ $log->getEventLabel() }}

            <strong>{{ $log->getSubjectLabel() }}</strong>
        </p>

        <!-- Show changes: "Status: Pending -> Active" -->
        <ul>
            @foreach($log->changes as $change)
                <li>
                     {{ $change->key }}:

                     <span class="text-red-500">{{ $change->old }}</span>
                     &rarr;

                     <!-- If it's a resolved model (e.g. status_id -> Status Model), link to it! -->
                     @if($change->relatedModel)
                        <a href="{{ route('statuses.show', $change->relatedModel) }}" class="text-green-500">
                            {{ $change->relatedModel->name }}
                        </a>
                     @else
                        <span class="text-green-500">{{ $change->new }}</span>
                     @endif
                </li>
            @endforeach
        </ul>
    </div>
@endforeach
```

## Documentation

- [Installation & Configuration](docs/installation.md) - Deep dive into setup and config options.
- [Logging Guide](docs/logging.md) - Best practices for logging model events.
- [Usage Patterns](docs/usage.md) - Advanced usage in Controllers, Views, and APIs.
- [Localization](docs/localization.md) - How to translate every aspect of your logs.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
