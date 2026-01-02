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
    // Automatically:
    // - Paginates the groups (lightweight query)
    // - Fetches the full Activity model for the `latest_id`
    // - Resolves relationships
    // - Hydrates the row with a `presentation` property
    // Note: Accepts both Eloquent Builder and Query Builder.
    $groupedRows = ActivityPresenter::presentGrouped($query);

    return view('audit-log.grouped', [
        'rows' => $groupedRows
    ]);
}
```

In your view:

```blade
@foreach($rows as $row)
    <!-- Access the DTO via the presentation property -->
    <div>{{ $row->presentation->subject_name }}</div>
    <div>{{ $row->presentation->diff }}</div>
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

The presenter returns `ActivityPresentationDTO` objects.

### System Properties

| Property    | Type     | Description               |
| :---------- | :------- | :------------------------ |
| **`id`**    | `int`    | ID of the log entry       |
| **`date`**  | `string` | Full date string          |
| **`diff`**  | `string` | Relative time             |
| **`event`** | `string` | **Translated** event name |

### Resolved Relations

| Property           | Type     | Description                                                        |
| :----------------- | :------- | :----------------------------------------------------------------- |
| **`user_name`**    | `string` | Name of the person who acted.                                      |
| **`subject_name`** | `string` | Name of the model (Auto-detects `audit_display`, `name`, `title`). |
| **`subject_type`** | `string` | **Translated** model class name.                                   |

### Changes (Translated vs Raw)

| Property             | Description                                                                   |
| :------------------- | :---------------------------------------------------------------------------- |
| **`old_values`**     | Keys are **translated** (e.g. "Name"). Values are **resolved** (e.g. "John"). |
| **`new_values`**     | Keys are **translated**. Values are **resolved**.                             |
| **`old_values_raw`** | **Original** DB keys (e.g. "first_name"). Original DB values (e.g. "John").   |
| **`new_values_raw`** | **Original** DB keys. Original DB values.                                     |

Use `_raw` properties when you need to perform logic in your view (e.g. `if ($key === 'status')`) or want to customize the display manually.

---

## 4. Advanced: The N+1 Problem Solved

`ActivityPresenter::presentCollection($activities)` scans the entire list first.

1. It collects all `user_id`, `category_id`, `product_id`, etc.
2. It executes **one query per model type**.
3. It maps the results back to the DTOs.
