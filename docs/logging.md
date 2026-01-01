# Logging Model Events

The package can automatically log events such as when a model is created, updated and deleted. To make this work all you need to do is let your model use the `Spatie\Activitylog\Traits\LogsActivity` trait.

As a bonus the package will also log the changed attributes for all these events when you define our own options method.

The trait contains an abstract method `getActivitylogOptions()` that you can use to customize options. It needs to return a `LogOptions` instance built from `LogOptions::defaults()` using fluent methods.

The attributes that need to be logged can be defined either by their name or you can put in a wildcard `['*']` to log any attribute that has changed.

Here's an example:

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class NewsItem extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'text'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logOnly(['name', 'text']);
        // Chain fluent methods for configuration options
    }
}
```

Note that we start from sensible defaults, but any of them can be overridden as needed by chaining fluent methods. Review the `Spatie\Activitylog\LogOptions` class for full list of supported options.

## Basics of Logging Configuration

If you want to log changes to all the `$fillable` attributes of the model, you can chain `->logFillable()` on the `LogOptions` class.

Alternatively, if you have a lot of attributes and used `$guarded` instead of `$fillable` you can also chain `->logUnguarded()` to add all attributes that are not listed in `$guarded`.

For both of these flags it will respect the possible wildcard `*` and add all `->logFillable()` or `->logUnguarded()` methods.
