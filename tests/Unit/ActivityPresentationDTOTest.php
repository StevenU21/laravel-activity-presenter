<?php

use Deifhelt\ActivityPresenter\Data\ActivityPresentationDTO;
use Deifhelt\ActivityPresenter\Data\AttributeChange;
use Deifhelt\ActivityPresenter\Services\TranslationService;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

// Create dummy models for testing smart resolution
class SmartModel extends Model
{
    protected $guarded = [];
    // Ensure getAttribute calls this
    public function getAuditDisplayAttribute()
    {
        return 'Audit Display Name';
    }
}
// Note: In strict unit tests without DB, `getAttribute` sometimes fails to see accessors if not in appends or if key missing.
// But mostly: the DTO code checks `getAttribute($attr)`. 
// If we ask for `audit_display`, `getAttribute('audit_display')` calling `getAuditDisplayAttribute` is standard Eloquent.
// However, the DTO logic iterates: `audit_display`, `name`, etc.
// The issue is likely that "SmartModel" is just a `new SmartModel` and DTO calls `getAttribute('audit_display')`. 
// Let's verify expectations.


class StandardModel extends Model
{
    protected $guarded = [];
    // Has 'name' attribute which should be picked up
}

function createDto(Activity $activity, array $config = [], array $resolvedModels = [])
{
    $presenter = new \Deifhelt\ActivityPresenter\ActivityLogPresenter(
        new \Deifhelt\ActivityPresenter\Services\RelationResolver($config),
        new TranslationService(),
        $config
    );

    // Reflection magic to use the protected builder or just mock the dependencies
    // simpler: use the public present() method but we need to mock RelationResolver to return our manual resolvedModels

    // Actually, let's manually build the DTO since we are testing the DTO logic, but DTO now requires huge constructor.
    // Better to use the Presenter or a helper that uses buildExhaustiveDto if it was public.
    // Since buildExhaustiveDto is protected, we should stick to testing `present()` if possible, 
    // but tests here seem to want to test DTO in isolation.
    // The DTO logic is now "dumb" (just data holding + some getters), the logic is in the Presenter.
    // So we should move most logic tests to `ActivityLogPresenterTest` or use reflection.

    // Let's use the presenter with a mocked resolver for the "resolved models" part.

    $mockResolver = Mockery::mock(\Deifhelt\ActivityPresenter\Services\RelationResolver::class);
    $mockResolver->shouldReceive('resolve')->andReturn($resolvedModels);

    $presenter = new \Deifhelt\ActivityPresenter\ActivityLogPresenter(
        $mockResolver,
        new TranslationService(),
        $config
    );

    return $presenter->present($activity);
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

    $config = [
        'hidden_attributes' => ['hidden'],
        'resolvers' => [],
        'label_attribute' => []
    ];

    $dto = createDto($activity, $config);

    expect($dto->activity->id)->toBe(1);
    expect($dto->activity->created_at->format('Y-m-d H:i:s'))->toBe('2023-01-01 12:00:00');

    // Check changes
    $change = $dto->changes->firstWhere('key', 'name');
    expect($change)->not->toBeNull();
    expect($change->old)->toBe('Old Name');
    expect($change->new)->toBe('New Name');

    // Check hidden
    $hiddenChange = $dto->changes->firstWhere('key', 'hidden');
    expect($hiddenChange)->toBeNull();
});

it('uses resolved models in changes', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->event = 'created';
    $activity->created_at = Carbon::now();
    $activity->properties = collect([
        'attributes' => ['user_id' => 10],
    ]);

    // We need a real model for type safety
    $mockUser = new class extends \Illuminate\Database\Eloquent\Model {
        protected $guarded = [];
    };
    $mockUser->full_name = 'Deifhelt';
    $mockUser->id = 10;


    $resolvedModels = [
        'App\Models\User' => [
            10 => $mockUser
        ]
    ];

    $config = [
        'resolvers' => ['user_id' => 'App\Models\User'],
        'label_attribute' => ['App\Models\User' => 'full_name'],
    ];

    $dto = createDto($activity, $config, $resolvedModels);

    $change = $dto->changes->firstWhere('key', 'user_id');
    expect($change->new)->toBe(10);
    expect($change->relatedModel)->toBe($mockUser);

    // We can't test "display string" from the DTO directly anymore because DTO doesn't format values.
    // The VIEW is responsible for doing `$change->relatedModel->full_name`.
});

it('smart resolves subject name using audit_display accessor via getSubjectLabel', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->event = 'created';
    $activity->created_at = Carbon::now();
    $activity->properties = collect([]);

    $subject = new SmartModel();
    $subject->id = 55;
    $activity->setRelation('subject', $subject);

    $dto = createDto($activity);

    expect($dto->getSubjectLabel())->toBe('Audit Display Name');
});

it('smart resolves subject name using standard name attribute via getSubjectLabel', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->event = 'created';
    $activity->created_at = Carbon::now();
    $activity->properties = collect([]);

    $subject = new StandardModel(['name' => 'Standard Name']);
    $subject->id = 55;
    $activity->setRelation('subject', $subject);

    $dto = createDto($activity);

    expect($dto->getSubjectLabel())->toBe('Standard Name');
});

it('resolves subject name using configured label attribute via getSubjectLabel', function () {
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

    $dto = createDto($activity, $config);

    expect($dto->getSubjectLabel())->toBe('My Title');
});
