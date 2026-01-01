# Laravel Activity Presenter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/deifhelt/laravel-activity-presenter.svg?style=flat-square)](https://packagist.org/packages/deifhelt/laravel-activity-presenter)
[![Total Downloads](https://img.shields.io/packagist/dt/deifhelt/laravel-activity-presenter.svg?style=flat-square)](https://packagist.org/packages/deifhelt/laravel-activity-presenter)
[![License](https://img.shields.io/packagist/l/deifhelt/laravel-activity-presenter.svg?style=flat-square)](https://packagist.org/packages/deifhelt/laravel-activity-presenter)

A powerful wrapper around `spatie/laravel-activitylog` that transforms raw activity logs into human-readable, resolved presentations with optimized relationship loading.

## Features

-   **Config-Driven**: Extensive configuration in `config/activity-presenter.php`
-   **Smart Resolution**: Automatically resolves related models (User, Subject) to prevent N+1 queries
-   **Rich Presentations**: Transforms raw log data into consistent, formatted DTOs
-   **Localization**: Built-in support for translating events, models, and attributes
-   **Custom Resolvers**: Define how specific attributes link to other models

> **Note**: This package wraps `spatie/laravel-activitylog`. For advanced recording features like logging model events, batch logging, or custom properties, see the [official Spatie documentation](https://spatie.be/docs/laravel-activitylog/v4/introduction).

## Installation

Install via Composer (Spatie Activitylog is included automatically):

```bash
composer require deifhelt/laravel-activity-presenter
```

Publish this package configuration:

```bash
php artisan vendor:publish --provider="Deifhelt\ActivityPresenter\ActivityPresenterServiceProvider"
```

This creates `config/activity-presenter.php` where you can define how models and attributes should be resolved.

Publish Spatie Activitylog configuration and migrations if you haven't already:

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

### Resolvers

Map attribute keys to their corresponding Eloquent models. This allows the presenter to automatically load the related model when displaying the log.

```php
// config/activity-presenter.php

'resolvers' => [
    'user_id' => \App\Models\User::class,
    'product_id' => \App\Models\Product::class,
    'category_id' => \App\Models\Category::class,
],
```

### Label Attributes

Define which attribute of a resolved model should be used as its label (e.g., 'name', 'title', 'email').

```php
// config/activity-presenter.php

'label_attribute' => [
    \App\Models\User::class => 'name',
    \App\Models\Product::class => 'title',
    \App\Models\Category::class => 'slug',
],
```

### Hidden Attributes

Specify attributes that should strictly be excluded from the presentation output.

```php
'hidden_attributes' => [
    'password',
    'remember_token',
    'two_factor_secret',
],
```

## Usage

Use the Facade to present activity logs. The presenter handles the heavy lifting of loading relationships and formatting data.

### Presenting a Collection

Efficiently processes a collection of activities, preloading all necessary relationships in a single pass to avoid N+1 issues.

```php
use Deifhelt\ActivityPresenter\Facades\ActivityPresenter;
use Spatie\Activitylog\Models\Activity;

$activities = Activity::latest()->get();

// Returns a Collection of ActivityPresentationDTOs
$presentedArgs = ActivityPresenter::presentCollection($activities);

foreach ($presentedArgs as $dto) {
    echo $dto->user_name;       // "John Doe" (Automatically resolved)
    echo $dto->event;          // "Created" (Translated)
    echo $dto->subject_name;   // "Project Alpha" (Resolved from subject relation)
    echo $dto->diff;           // "2 hours ago"
}
```

### Presenting a Single Activity

```php
$activity = Activity::find(1);
$dto = ActivityPresenter::present($activity);
```

## Translations

Easily translate events, model names, and attributes.

### Publishing Translations

```bash
php artisan vendor:publish --tag=activity-presenter-translations
```

This will publish language files to `resources/lang/vendor/activity-presenter`.

### Customizing Translations

**Events**:

```php
// resources/lang/vendor/activity-presenter/en/logs.php
'events' => [
    'created' => 'Created',
    'updated' => 'Updated',
    'deleted' => 'Deleted',
],
```

**Models**:

```php
'models' => [
    'User' => 'User',
    'Post' => 'Article',
],
```

**Attributes**:

```php
'attributes' => [
    'title' => 'Title',
    'content' => 'Body text',
    'is_published' => 'Published Status',
],
```

> **Note**: The system automatically respects your `config('app.locale')`.

## Documentation

-   [Installation & Configuration](docs/installation.md)
-   [Logging](docs/logging.md)
-   [Usage Guide](docs/usage.md)
-   [Localization](docs/localization.md)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
