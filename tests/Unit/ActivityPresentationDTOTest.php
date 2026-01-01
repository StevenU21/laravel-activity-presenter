<?php

use Deifhelt\ActivityPresenter\Data\ActivityPresentationDTO;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Carbon;

it('transforms activity into dto correctly', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->created_at = Carbon::parse('2023-01-01 12:00:00');
    $activity->event = 'updated';
    $activity->properties = collect([
        'old' => ['name' => 'Old Name', 'hidden' => 'secret'],
        'attributes' => ['name' => 'New Name', 'hidden' => 'secret_new'],
    ]);

    // Mock user interaction if needed, but for DTO unit test we focus on data mapping
    // We can simulate resolved models passing
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
});

it('uses resolved models for values', function () {
    $activity = new Activity();
    $activity->id = 1;
    $activity->event = 'created';
    $activity->created_at = Carbon::now();
    $activity->properties = collect([
        'attributes' => ['user_id' => 10],
    ]);

    // Mock resolved model
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
});
