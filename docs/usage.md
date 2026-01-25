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

### Presenting a Collection (Standard)

Use `presentCollection` when dealing with lists. This method is optimized to prevent N+1 queries.

```php
use Deifhelt\ActivityPresenter\Facades\ActivityPresenter;
use Spatie\Activitylog\Models\Activity;

public function index()
{
    // 1. Retrieve
    $activities = Activity::with('causer', 'subject')
        ->latest()
        ->paginate(20);

    // 2. Present
    $presentedActivities = ActivityPresenter::presentCollection($activities);

    // 3. Display
    return view('audit-log.index', [
        'activities' => $presentedActivities,
        'links' => $activities->links()
    ]);
}
```

### Presenting Grouped Activities (Index Pattern)

A common UI pattern is to show a list of _subjects_ that were recently updated (e.g., "Invoice #123 (Updated 5 mins ago)"), rather than every single field change as a separate row.

To support this efficiently, use `presentGrouped`.

```php
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

public function index()
{
    // 1. Group by Subject (Latest activity per subject)
    $query = Activity::query()
        ->select('subject_type', 'subject_id', DB::raw('MAX(id) as latest_id'))
        ->groupBy('subject_type', 'subject_id')
        ->latest('latest_id');

    // 2. Present Grouped
    // Note: Accepts both Eloquent Builder and Query Builder.
    // Optional arguments: perPage (default 10), latestIdColumn (default 'latest_id'), loadRelations (callback)
    $groupedRows = ActivityPresenter::presentGrouped(
        query: $query,
        perPage: 15,
        loadRelations: function ($query) {
             // 1. Eager load specific relations
             $query->with(['subject.client']);
        },
        afterFetch: function ($activities) {
             // 2. Perform logic on the Collection (e.g. loadMorph)
             // $activities->loadMorph('subject', [ ... ]);
        },
        mapGroupRow: function ($row, $activity, $presentation) {
            // 3. Transform into your Project's DTO (Best Practice)
            // This decouples the library from your View/Routing logic.
            return new \App\DTOs\AuditIndexRow(
                date: $presentation->activity->created_at,
                subject: $presentation->getSubjectLabel(),
                event: $presentation->getEventLabel(),
                user: $presentation->getCauserLabel(),
                filterUrl: route('audit.index', ['type' => $row->encoded_subject_type]),
                detailsUrl: route('audit.show', $activity->id)
            );
        }
    );

    return view('audit-log.grouped', [
        'rows' => $groupedRows
    ]);
}
```

In your view, you now have a clean object:

```blade
@foreach($rows as $row)
    <tr>
        <td>{{ $row->subject }}</td>
        <td>{{ $row->event }}</td>
        <td><a href="{{ $row->filterUrl }}">Filter</a></td>
        <td><a href="{{ $row->detailsUrl }}">View Details</a></td>
    </tr>
@endforeach
```

---

## 2. Routing Helpers

When building URLs for your audit logs (e.g. filtering by subject type), avoid putting full namespaces like `App\Models\Invoice` in the URL.

The presenter offers helpers to encode/decode these classes cleanly.

### Setup (Optional)

Define generic aliases in `config/activity-presenter.php`:

```php
'subject_aliases' => [
    'App\Models\Invoice' => 'invoice',
    'App\Models\User' => 'user',
],
```

### Usage

```php
// In Controller or View
$typeParam = ActivityPresenter::encodeSubjectType($activity->subject_type);
// Returns 'invoice' (if aliased) or URL-safe Base64

// In Route Controller
public function index(Request $request) {
    if ($request->has('type')) {
        $modelClass = ActivityPresenter::decodeSubjectType($request->input('type'));
        // Returns 'App\Models\Invoice'
        $query->where('subject_type', $modelClass);
    }
}
```

---

## 3. Viewing Data (The DTO)

The presenter returns `ActivityPresentationDTO` objects, which serve as a rich wrapper around your specific log.

### Core Properties

| Property       | Type         | Description                                                                                                                          |
| :------------- | :----------- | :----------------------------------------------------------------------------------------------------------------------------------- |
| **`activity`** | `Activity`   | The original Spatie Activitylog model. Access properties like `$log->activity->created_at` or `$log->activity->properties` directly. |
| **`causer`**   | `?Model`     | The resolved User model who performed the action. Can be null (System).                                                              |
| **`subject`**  | `?Model`     | The resolved Subject model. Can be null if deleted.                                                                                  |
| **`changes`**  | `Collection` | A collection of `AttributeChange` objects representing what changed.                                                                 |

### Helper Methods

| Method                  | Returns  | Description                                                                                           |
| :---------------------- | :------- | :---------------------------------------------------------------------------------------------------- |
| **`getCauserLabel()`**  | `string` | Returns the name of the user (configurable via `label_attribute`). Defaults to "System" or "Unknown". |
| **`getSubjectLabel()`** | `string` | Returns the display name of the subject. Checks `audit_display`, `name`, `title`, etc. gracefully.    |
| **`getEventLabel()`**   | `string` | Returns the **translated** event name (e.g., "Created" -> "Creado").                                  |

### Working with Changes (`AttributeChange`)

The `changes` collection contains strict objects representing each field modification.

| Property           | Type     | Description                                                                                    |
| :----------------- | :------- | :--------------------------------------------------------------------------------------------- |
| **`key`**          | `string` | The attribute name (e.g., "status_id").                                                        |
| **`old`**          | `mixed`  | The original old value (e.g., `1`).                                                            |
| **`new`**          | `mixed`  | The original new value (e.g., `2`).                                                            |
| **`relatedModel`** | `?Model` | If configured in `resolvers`, this holds the related model (e.g., The Status model with ID 2). |

#### Example: Displaying a Status Change

```blade
@foreach($log->changes as $change)
    <li>
        <strong>{{ $change->key }}</strong>:

        @if($change->relatedModel)
             <!-- Optimized: We have the model instance ready to use! -->
             <a href="{{ route('statuses.show', $change->relatedModel) }}">
                 {{ $change->relatedModel->name }}
             </a>
        @else
             {{ $change->old }} -> {{ $change->new }}
        @endif
    </li>
@endforeach
```

## 4. Advanced: The N+1 Problem Solved

`ActivityPresenter::presentCollection($activities)` scans the entire list first.

1. It collects all `user_id`, `category_id`, `product_id`, etc.
2. It executes **one query per model type**.
3. It maps the results back to the DTOs.
