# Presenting Activity (Usage)

This package shines when you need to display activity logs to users. It solves the common N+1 problem when loading related models and provides a consistent Data Transfer Object (DTO) for your views (Blade, Vue, React, etc.).

## The Presenter

Use the `ActivityPresenter` facade to transform activities.

```php
use Deifhelt\ActivityPresenter\Facades\ActivityPresenter;
use Spatie\Activitylog\Models\Activity;

// Controller
public function index()
{
    // 1. Get Activities (standard Eloquent)
    $activities = Activity::latest()->paginate(20);

    // 2. Present Collection
    // This efficiently preloads related users, subjects, etc. defined in your config.
    $presented = ActivityPresenter::presentCollection($activities);

    // 3. Return to View
    return inertia('Activity/Index', [
        'activities' => $presented
    ]);
}
```

## The Data Structure (DTO)

The `presentCollection` method returns a collection of `ActivityPresentationDTO` objects. This guarantees a consistent structure:

| Property       | Type     | Description                                                       |
| :------------- | :------- | :---------------------------------------------------------------- |
| `id`           | `int`    | The activity ID                                                   |
| `date`         | `string` | Formatted date (Y-m-d H:i:s)                                      |
| `diff`         | `string` | Human readable diff (e.g. "2 hours ago")                          |
| `user_name`    | `string` | The resolved name of the Causer (e.g. "John Doe")                 |
| `event`        | `string` | The event name (created, updated, deleted)                        |
| `subject_name` | `string` | The resolved name of the Subject (e.g. "Project X")               |
| `old_values`   | `array`  | Array of original values (IDs resolved to names where configured) |
| `new_values`   | `array`  | Array of new values (IDs resolved to names where configured)      |

## Configuration-Driven Resolution

The magic happens in `config/activity-presenter.php`. You define which fields map to which models.

```php
// config/activity-presenter.php

return [
    'resolvers' => [
        // Automatically turns 'user_id' => 5 into 'user_id' => 'Deifhelt' in the DTO
        'user_id' => \App\Models\User::class,
    ],

    'label_attribute' => [
        // Tells the presenter to use 'full_name' when displaying a User
        \App\Models\User::class => 'full_name',
    ]
];
```
