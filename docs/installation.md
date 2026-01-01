# Installation & Configuration

## Requirements

-   PHP 8.2+
-   Laravel 11.0+

## Installation

Install the package via composer:

```bash
composer require deifhelt/laravel-activity-presenter
```

> [!NOTE] > **Wrapper Notification**: This package automatically installs `spatie/laravel-activitylog` as a dependency. You do not need to install it separately. Refer to the [official Spatie documentation](https://spatie.be/docs/laravel-activitylog/v4/introduction) for deep dives.

## Setup

### 1. Database & Migrations

Publish the migration file from the underlying Spatie package:

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
```

Run the migration to create the `activity_log` table:

```bash
php artisan migrate
```

### 2. Custom Database Connection (Optional)

If you want your activities to be stored in a special database connection (e.g., for audit separation), define `ACTIVITY_LOGGER_DB_CONNECTION` in your `.env` file:

```env
ACTIVITY_LOGGER_DB_CONNECTION=audit_db
```

### 3. Publishing Configuration

**Activity Presenter Config (Recommended)**
This config controls how activities are resolved and presented by this package.

```bash
php artisan vendor:publish --tag="activity-presenter-config"
```

**Activity Log Config (Optional)**
If you need to tweak the low-level logging behavior (table name, retention, etc.).

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
```

> [!IMPORTANT]
> After configuring everything, remember to clear the application config cache:
>
> ```bash
> php artisan config:clear
> ```
