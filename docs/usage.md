# Usage Guide

This guide covers how to use the `ActivityPresenter` to display logs in your application effectively.

## The Workflow

The typical workflow for displaying activity logs involves three steps:

1.  **Retrieve**: Fetch the logs using standard Eloquent methods.
2.  **Present**: Pass the logs through the `ActivityPresenter` to resolve relations and format data.
3.  **Display**: Use the resulting DTOs in your view or API response.

---

## 1. Controller Usage

The most common place to use the presenter is in your Controller's `index` or `show` method.

### Presenting a Collection (Recommended)

Use `presentCollection` when dealing with lists. This method is optimized to prevent N+1 queries by batch-loading all related models (Users, Subjects, etc.) in a single go.

```php
use Deifhelt\ActivityPresenter\Facades\ActivityPresenter;
use Spatie\Activitylog\Models\Activity;

public function index()
{
    // 1. Retrieve
    $activities = Activity::with('causer', 'subject') // Optional: Eager load core relations
        ->latest()
        ->paginate(20);

    // 2. Present
    // This returns a new Collection of ActivityPresentationDTOs
    $presentedActivities = ActivityPresenter::presentCollection($activities);

    // 3. Display
    return view('audit-log.index', [
        'activities' => $presentedActivities,
        'links' => $activities->links() // Keep pagination links
    ]);
}
```

### Presenting a Single Item

If you are showing the details of a single log entry:

```php
public function show($id)
{
    $activity = Activity::findOrFail($id);

    $dto = ActivityPresenter::present($activity);

    return view('audit-log.show', ['activity' => $dto]);
}
```

---

## 2. Viewing Data (The DTO)

The presenter returns `ActivityPresentationDTO` objects. These are simple data containers with typed properties, making them reliable to use in views.

### System Properties

| Property    | Type     | Description               | Example                        |
| :---------- | :------- | :------------------------ | :----------------------------- |
| **`id`**    | `int`    | ID of the log entry       | `105`                          |
| **`date`**  | `string` | Full date string          | `"2023-10-25 14:30:00"`        |
| **`diff`**  | `string` | Relative time             | `"2 hours ago"`                |
| **`event`** | `string` | **Translated** event name | `"Updated"` (or "Actualizado") |

### Resolved Relations

These properties are automatically resolved from your database or configuration.

| Property           | Type     | Description                            | Example                     |
| :----------------- | :------- | :------------------------------------- | :-------------------------- |
| **`user_name`**    | `string` | Name of the person who did the action. | `"John Doe"` or `"System"`  |
| **`subject_name`** | `string` | Name of the model being acted upon.    | `"Project Alpha"`           |
| **`subject_type`** | `string` | **Translated** model class name.       | `"Project"` (or "Proyecto") |

### Changes (Old vs New)

The explicit values that changed.

| Property         | Type    | Description                                                                                   |
| :--------------- | :------ | :-------------------------------------------------------------------------------------------- |
| **`old_values`** | `array` | Associative array of original values. Keys are **translated**. IDs are **resolved** to names. |
| **`new_values`** | `array` | Associative array of new values. Same resolution logic applies.                               |

---

## 3. Blade Example

Here is a robust example of a table row in Blade:

```blade
<tr>
    <!-- Date & User -->
    <td>
        <div class="font-bold">{{ $activity->user_name }}</div>
        <div class="text-xs text-gray-500">{{ $activity->diff }}</div>
    </td>

    <!-- Action -->
    <td>
        <span class="badge badge-info">{{ $activity->event }}</span>
        <span class="font-mono text-sm">{{ $activity->subject_type }}</span>:
        <strong>{{ $activity->subject_name }}</strong>
    </td>

    <!-- Details -->
    <td>
        <div class="text-sm">
            @if(count($activity->old_values) > 0)
                @foreach($activity->new_values as $key => $newValue)
                    <div class="mb-1">
                        <span class="text-gray-600">{{ $key }}:</span>
                        <del class="text-red-500">{{ $activity->old_values[$key] ?? '—' }}</del>
                        &rarr;
                        <span class="text-green-600">{{ $newValue }}</span>
                    </div>
                @endforeach
            @else
                <span class="italic text-gray-400">No attribute changes recorded</span>
            @endif
        </div>
    </td>
</tr>
```

---

## 4. Advanced: The N+1 Problem Solved

### The Problem

Imagine showing a list of 50 activities.

-   10 are "Created Order" (Linked to `User`)
-   10 are "Updated Product" (Linked to `Category`)

A naive implementation loops through and calls `$activity->causer->name` or loads `$category = Category::find($activity->properties['category_id'])`. This results in **dozens of database queries** for a simple page.

### The Solution

`ActivityPresenter::presentCollection($activities)` scans the entire list first.

1. It collects all `user_id`, `category_id`, `product_id`, etc.
2. It executes **one query per model type** (e.g., `SELECT * FROM users WHERE id IN (...)`).
3. It maps the results back to the DTOs.

This ensures your generic activity feed remains performant even as it grows.
