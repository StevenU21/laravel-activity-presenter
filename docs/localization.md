# Localization (Multi-Language Support)

Laravel Activity Presenter comes with built-in localization support. This ensures that your activity logs—which are technically stored as "keys" (e.g., `created`, `updated`)—are displayed to the user in their native language (e.g., "Creado", "Actualizado").

## How Resolution Works

The presenter checks for translations in the following namespace: `activity-presenter::logs`.

It attempts to translate keys in this specific order:

| Type          | Key Pattern              | Example Key            | Fallback                      |
| :------------ | :----------------------- | :--------------------- | :---------------------------- |
| **Event**     | `events.{event_name}`    | `events.created`       | `ucfirst($event)` ("Created") |
| **Model**     | `models.{ClassBasename}` | `models.User`          | Class Basename ("User")       |
| **Attribute** | `attributes.{column}`    | `attributes.is_active` | Readable title ("Is Active")  |

## Publishing Translations

To customize the text or add a new language, publish the translation files:

```bash
php artisan vendor:publish --tag="activity-presenter-translations"
```

This creates the directory `resources/lang/vendor/activity-presenter`.

## Customization Guide

### 1. Events

Defined in `logs.php`. Most common events are pre-filled, but you can add custom ones triggered by your application.

```php
// resources/lang/vendor/activity-presenter/en/logs.php

'events' => [
    'created' => 'Created',
    'updated' => 'Updated',
    'deleted' => 'Deleted',
    'restored' => 'Restored',

    // Custom events
    'published' => 'Published',
    'archived' => 'Archived',
],
```

### 2. Models (Subjects)

Map your Eloquent model class names to human-readable names.

```php
'models' => [
    'User' => 'System User',
    'BlogPost' => 'Article',
    'ProductVariant' => 'Product Option',
],
```

### 3. Attributes

Map database column names to user-friendly labels.

```php
'attributes' => [
    'user_id' => 'Owner',
    'category_id' => 'Category',
    'is_visible' => 'Visibility Status',
    'price_cents' => 'Price',
],
```

## Runtime Language Switching

The package relies on `Illuminate\Support\Facades\Lang`. This means it automatically respects the active locale of your application.

```php
// In a Middleware or Controller
app()->setLocale('es');

// Presenter will now output Spanish
$dto = ActivityPresenter::present($activity);
echo $dto->event; // "Creado"
```
