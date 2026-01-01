# Laravel Activity Presenter

A configuration-driven package to present Spatie Activity Log activities with resolved relationships and consistent DTOs.

> [!NOTE] > **Wrapper Package**: This library wraps `spatie/laravel-activitylog`. It installs it automatically, so you get all the power of the original package plus the presentation layer.

## Documentation

-   [Installation & Configuration](docs/installation.md)
-   [Logging Model Events](docs/logging.md)
-   [Presenting Activity (Usage)](docs/usage.md)
-   [Localization (Multi-Language)](docs/localization.md)

## Quick Start

1.  **Install**:

    ```bash
    composer require deifhelt/laravel-activity-presenter
    ```

2.  **Publish Config**:

    ```bash
    php artisan vendor:publish --tag="activity-presenter-config"
    ```

3.  **Use**:
    ```php
    $presented = \Deifhelt\ActivityPresenter\Facades\ActivityPresenter::presentCollection($activities);
    ```

## Testing

```bash
composer test
```
