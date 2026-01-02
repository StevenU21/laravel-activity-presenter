# Logging Model Events

This package relies on `spatie/laravel-activitylog` to record events. This guide provides a quick reference on how to set up your models to ensure they generate logs that are easy to present.

## Basic Setup

Add the `LogsActivity` trait to any Eloquent model you want to track.

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Product extends Model
{
    use LogsActivity;

    // Required by Spatie: Configure what to log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll() // Log all attributes in $fillable
            ->logOnlyDirty(); // Only log fields that actually changed
    }
}
```

## Naming Your Logs

By default, the log name is `default`. It's often useful to organize logs by channel (e.g., `system`, `orders`, `auth`).

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->useLogName('inventory')
        ->logFillable();
}
```

## Logging Related Models

For complex actions, you might want to log custom properties that `ActivityPresenter` can later resolve.

### Example: Custom Event

```php
activity()
    ->performedOn($order)
    ->causedBy($user)
    ->withProperties([
        'custom_prop' => 'value',
        'category_id' => $category->id // Presenter can resolve this!
    ])
    ->log('custom_action');
```

If you configured `category_id` in `config/activity-presenter.php`, the presenter will automatically look up the Category model when displaying this log, even though it's inside a JSON property.

## Best Practices for Presentation

1.  **Use `logOnlyDirty()`**: Prevents cluttering the database with updates where nothing changed.
2.  **Log IDs for Relations**: Always log `user_id`, `team_id`, etc., in the properties if they aren't the main subject. The Presenter handles resolving them to names.
3.  **Group Actions**: Use `batch` logging if available/needed for atomic operations.

Refer to the [Spatie Documentation](https://spatie.be/docs/laravel-activitylog/v4/basic-usage/logging-activity) for the full API.
