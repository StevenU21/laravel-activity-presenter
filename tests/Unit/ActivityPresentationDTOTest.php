<?php

use Deifhelt\ActivityPresenter\Data\ActivityPresentationDTO;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

// Create dummy models for testing smart resolution
class SmartModel extends Model
{
    protected $guarded = [];
    public function getAuditDisplayAttribute()
    {
        return 'Audit Display Name';
    }
}

class StandardModel extends Model
{
    protected $guarded = [];
    // Has 'name' attribute which should be picked up
}

it('transforms activity into dto correctly', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->created_at = Carbon::parse('2023-01-01 12:00:00');
    $activity->event = 'updated';
    $activity->properties = collect([
        'old' => ['name' => 'Old Name', 'hidden' => 'secret'],
        'attributes' => ['name' => 'New Name', 'hidden' => 'secret_new'],
    ]);

    $resolvedModels = [];
    $config = [
        'hidden_attributes' => ['hidden'],
        'resolvers' => [],
        'label_attribute' => []
    ];

    $dto = ActivityPresentationDTO::fromActivity($activity, $resolvedModels, $config);

    expect($dto->id)->toBe(1);
    expect($dto->date)->toBe('2023-01-01 12:00:00');
    expect($dto->old_values)->not->toHaveKey('hidden');
    expect($dto->new_values)->toHaveKey('Name', 'New Name');

    // Check raw values
    expect($dto->old_values_raw)->toHaveKey('hidden', 'secret'); // Should persist even if hidden in presentation
    expect($dto->new_values_raw)->toHaveKey('name', 'New Name');
});

it('uses resolved models for values', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->event = 'created';
    $activity->created_at = Carbon::now();
    $activity->properties = collect([
        'attributes' => ['user_id' => 10],
    ]);

    $mockUser = new stdClass();
    $mockUser->full_name = 'Deifhelt';

    $resolvedModels = [
        'App\Models\User' => [
            10 => $mockUser
        ]
    ];

    $config = [
        'resolvers' => ['user_id' => 'App\Models\User'],
        'label_attribute' => ['App\Models\User' => 'full_name'],
    ];

    $dto = ActivityPresentationDTO::fromActivity($activity, $resolvedModels, $config);

    expect($dto->new_values['User id'])->toBe('Deifhelt');
    expect($dto->new_values_raw['user_id'])->toBe(10); // Check raw is original ID
});

it('smart resolves subject name using audit_display accessor', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->event = 'created';
    $activity->created_at = Carbon::now();
    $activity->properties = collect([]);

    $subject = new SmartModel();
    $subject->id = 55;
    $activity->setRelation('subject', $subject);

    $config = []; // No explicit config

    $dto = ActivityPresentationDTO::fromActivity($activity, [], $config);

    expect($dto->subject_name)->toBe('Audit Display Name');
});

it('smart resolves subject name using standard name attribute', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->event = 'created';
    $activity->created_at = Carbon::now();
    $activity->properties = collect([]);

    $subject = new StandardModel(['name' => 'Standard Name']);
    $subject->id = 55;
    $activity->setRelation('subject', $subject);

    $config = [];

    $dto = ActivityPresentationDTO::fromActivity($activity, [], $config);

    expect($dto->subject_name)->toBe('Standard Name');
});

it('resolves subject name using configured label attribute', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->event = 'created';
    $activity->created_at = Carbon::now();
    $activity->properties = collect([]);

    $subject = new StandardModel(['title' => 'My Title']); // Using 'title' but not standard attribute
    $subject->id = 55;
    $activity->setRelation('subject', $subject);

    $config = [
        'label_attribute' => [StandardModel::class => 'title']
    ];

    $dto = ActivityPresentationDTO::fromActivity($activity, [], $config);

    expect($dto->subject_name)->toBe('My Title');
});
