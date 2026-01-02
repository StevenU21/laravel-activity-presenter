# Installation & Configuration

## Requirements

-   **PHP**: 8.1 or higher
-   **Laravel**: 10.0, 11.0, or 12.0

## Installation

Install the package via composer:

```bash
composer require deifhelt/laravel-activity-presenter
```

> [!NOTE] > **Dependencies**: This package automatically installs `spatie/laravel-activitylog` v4. If you are already using it, no changes are needed.

## Configuration Setup

### 1. Publish Package Configuration

Run the following command to publish the `config/activity-presenter.php` file:

```bash
php artisan vendor:publish --tag="activity-presenter-config"
```

### 2. Configure Resolvers

The configuration file is the heart of this package. It tells the presenter how to turn raw IDs into readable names.

Open `config/activity-presenter.php`:

#### resolvers

Map attribute names (from the log properties) to their Eloquent implementation. This allows the system to look up the related model.

```php
'resolvers' => [
    'user_id' => \App\Models\User::class,
    'client_id' => \App\Models\Client::class,
    'product_id' => \App\Models\Product::class,
],
```

#### label_attribute

Define which column to display for each model. If not specified, it defaults to `name` or `title`.

```php
'label_attribute' => [
    \App\Models\User::class => 'full_name', // Uses $user->full_name
    \App\Models\Client::class => 'company_name',
],
```

#### hidden_attributes

List attributes that should never be shown in the presented output (e.g., sensitive data or internal logic fields).

```php
'hidden_attributes' => [
    'password',
    'remember_token',
    'api_key',
    'updated_at', // Often noisy and unnecessary to show as a "change"
],
```

### 3. Database Setup

If you haven't set up the Activity Log table yet:

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

## Next Steps

Now that you have configured the package, check out:

-   [Logging Guide](logging.md) to start recording activities.
-   [Usage Guide](usage.md) to learn how to display them.
