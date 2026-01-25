<?php

use Deifhelt\ActivityPresenter\Services\RelationResolver;
use Deifhelt\ActivityPresenter\Services\TranslationService;
use Deifhelt\ActivityPresenter\ActivityLogPresenter;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

// Define a test model for alias testing
class TestModel extends Model
{
    protected $table = 'test_models';
}

beforeEach(function () {
    // Create test tables
    Schema::create('test_models', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

function createPresenter(array $config = [])
{
    return new ActivityLogPresenter(
        new RelationResolver($config),
        new TranslationService(),
        $config
    );
}

it('encodes subject type using base64 by default', function () {
    $presenter = createPresenter([]);

    $class = 'App\Models\Test';
    $encoded = $presenter->encodeSubjectType($class);

    // Base64 of 'App\Models\Test' is QXBwXE1vZGVsc1xUZXN0
    // URL safe replacement: + -> -, / -> _, = -> ''
    expect($encoded)->toBe(str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($class)));
});

it('encodes subject type using configured alias', function () {
    $config = [
        'subject_aliases' => [
            'App\Models\Invoice' => 'invoice',
        ]
    ];
    $presenter = createPresenter($config);

    expect($presenter->encodeSubjectType('App\Models\Invoice'))->toBe('invoice');
});

it('decodes subject type using base64', function () {
    $presenter = createPresenter([]);
    $class = 'App\Models\Test';
    $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($class));

    expect($presenter->decodeSubjectType($encoded))->toBe($class);
});

it('decodes subject type using configured alias', function () {
    $config = [
        'subject_aliases' => [
            'App\Models\Invoice' => 'invoice',
        ]
    ];
    $presenter = createPresenter($config);

    expect($presenter->decodeSubjectType('invoice'))->toBe('App\Models\Invoice');
});

it('presents grouped activities correctly', function () {
    // Create Activities
    Activity::create(['event' => 'created', 'subject_type' => TestModel::class, 'subject_id' => 1, 'description' => 'created test']);
    Activity::create(['event' => 'updated', 'subject_type' => TestModel::class, 'subject_id' => 1, 'description' => 'updated test']); // Latest

    Activity::create(['event' => 'created', 'subject_type' => TestModel::class, 'subject_id' => 2, 'description' => 'created test 2']); // Only one

    // Prepare grouped query
    $query = Activity::query()
        ->select('subject_type', 'subject_id', DB::raw('MAX(id) as latest_id'))
        ->groupBy('subject_type', 'subject_id')
        ->orderBy('latest_id', 'desc');

    $presenter = createPresenter([]);

    $paginator = $presenter->presentGrouped($query);

    expect($paginator->count())->toBe(2);

    $items = $paginator->getCollection();

    $first = $items->first();
    expect($first->presentation)->toBeInstanceOf(\Deifhelt\ActivityPresenter\Data\ActivityPresentationDTO::class);
    expect($first->presentation->getEventLabel())->toBe('Created'); // Default translation for 'created'

    $second = $items->last();
    expect($second->presentation->getEventLabel())->toBe('Updated');
});

it('handles presentGrouped gracefully with no results', function () {
    $query = Activity::query()
        ->select('subject_type', 'subject_id', DB::raw('MAX(id) as latest_id'))
        ->groupBy('subject_type', 'subject_id');

    $presenter = createPresenter([]);
    $paginator = $presenter->presentGrouped($query);

    expect($paginator->count())->toBe(0);
});

it('presents grouped activities with custom per page', function () {
    // Create 15 items
    for ($i = 1; $i <= 15; $i++) {
        Activity::create(['event' => 'created', 'subject_type' => TestModel::class, 'subject_id' => $i, 'description' => "test $i"]);
    }

    $query = Activity::query()
        ->select('subject_type', 'subject_id', DB::raw('MAX(id) as latest_id'))
        ->groupBy('subject_type', 'subject_id');

    $presenter = createPresenter([]);

    // Request 5 per page
    $paginator = $presenter->presentGrouped($query, 5);

    expect($paginator->perPage())->toBe(5);
    expect($paginator->count())->toBe(5);
    expect($paginator->total())->toBe(15);
    expect($paginator->lastPage())->toBe(3);
});

it('presents grouped activities with advanced options', function () {
    // Create activities
    Activity::create(['event' => 'created', 'subject_type' => TestModel::class, 'subject_id' => 1, 'description' => 'test 1']);

    $query = Activity::query()
        ->select('subject_type', 'subject_id', DB::raw('MAX(id) as max_id_alias'))
        ->groupBy('subject_type', 'subject_id');

    $presenter = createPresenter([]);

    // Test with custom max_id column and loadRelations callback
    $paginator = $presenter->presentGrouped(
        query: $query,
        latestIdColumn: 'max_id_alias',
        loadRelations: function ($q) {
            $q->where('id', '>', 0); // Dummy condition to verify callback is called
        },
        afterFetch: function ($collection) {
            // Verify we received a collection of models
            expect($collection)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            // Mutate something to verify it persists (side-effect)
            if ($collection->isNotEmpty()) {
                $collection->first()->was_processed_by_hook = true;
            }
        },
        mapGroupRow: function ($row, $activity, $presentation) {
            // Return a simple custom object (simulating a ViewModel)
            return (object) [
                'custom_title' => $presentation->getSubjectLabel() . ' (' . $presentation->getEventLabel() . ')',
                'original_id' => $activity->id,
                'type_link' => $row->encoded_subject_type,
            ];
        }
    );

    $item = $paginator->first();

    // Verify mapped output
    expect($item->custom_title)->toContain('Created'); // 'Created' or similar
    expect($item->original_id)->toBe(1);
    expect($item->type_link)->not->toBeNull();
});
