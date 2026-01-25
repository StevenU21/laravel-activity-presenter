<?php

use Deifhelt\ActivityPresenter\Facades\ActivityPresenter;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

// Mock models can be defined here or in a support file
class FeatureTestUser extends Model
{
    protected $table = 'feature_test_users';
    protected $guarded = [];
}

it('can resolve activity with related model', function () {
    Schema::create('feature_test_users', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('activity-presenter.resolvers', [
        'user_id' => FeatureTestUser::class,
    ]);
    config()->set('activity-presenter.label_attribute', [
        FeatureTestUser::class => 'name',
    ]);

    $user = FeatureTestUser::create(['name' => 'John Doe']);

    $activity = Activity::create([
        'description' => 'updated',
        'event' => 'updated',
        'properties' => [
            'old' => ['user_id' => $user->id, 'foo' => 'bar'],
            'attributes' => ['user_id' => $user->id, 'foo' => 'baz'],
        ]
    ]);

    $dto = ActivityPresenter::present($activity);

    $userIdChange = $dto->changes->firstWhere('key', 'user_id');
    expect($userIdChange)->not->toBeNull();
    // In strict mode raw values are preserved. The view handles the display resolution.
    // However, if we want to verify resolution HAPPENED, check relatedModel.
    expect($userIdChange->relatedModel)->toBeInstanceOf(FeatureTestUser::class);
    expect($userIdChange->relatedModel->name)->toBe('John Doe');

    $fooChange = $dto->changes->firstWhere('key', 'foo');
    expect($fooChange->old)->toBe('bar');
    expect($fooChange->new)->toBe('baz');
});
