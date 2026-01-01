# Localization (Multi-Language Support)

This package uses Laravel's native localization system to translate activity events, model names, and attributes. This allows your application to support multiple languages (e.g., English, Spanish) seamlessly.

## How it works

The package enables the `activity-presenter` translation namespace. It looks for translations in the following order:

1.  **Events**: `activity-presenter::logs.events.{event_name}` (e.g., `created`, `updated`)
2.  **Models**: `activity-presenter::logs.models.{ModelName}` (e.g., `User`, `Post`)
3.  **Attributes**: `activity-presenter::logs.attributes.{attribute_name}` (e.g., `is_active`, `amount`)

If no translation is found, it falls back to a readable default (e.g., "Created", "User", "Is Active").

## Customizing Translations

To customize the texts, you can publish the language files:

```bash
php artisan vendor:publish --tag="activity-presenter-translations"
```

This will publish the files to `resources/lang/vendor/activity-presenter`. You can then edit them or add new languages.

### Example: Adding Spanish

If you publish the files, you will see `es/logs.php`:

```php
// resources/lang/vendor/activity-presenter/es/logs.php
return [
    'events' => [
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
    ],
    'models' => [
        'User' => 'Usuario',
        'Order' => 'Pedido',
    ],
    'attributes' => [
        'amount' => 'Monto Total',
    ]
];
```

## Changing Language

Since we use Laravel's standard localization, changing the app locale automatically updates the logs:

```php
app()->setLocale('es');
// $presenter->event will now be "Creado"
```
