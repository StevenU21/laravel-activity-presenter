<?php

use Deifhelt\ActivityPresenter\Services\RelationResolver;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

// Mock for unit test
class UnitRelUser extends Model
{
    protected $table = 'unit_rel_users';
    protected $guarded = [];
}

it('identifies and loads related models from config', function () {
    Schema::create('unit_rel_users', function ($table) {
        $table->id();
        $table->timestamps();
    });

    $user1 = UnitRelUser::create();
    $user2 = UnitRelUser::create();

    $config = [
        'resolvers' => [
            'user_id' => UnitRelUser::class,
        ]
    ];

    $resolver = new RelationResolver($config);

    // Mock activities
    $activity1 = new Activity();
    $activity1->properties = collect(['attributes' => ['user_id' => $user1->id]]);

    $activity2 = new Activity();
    $activity2->properties = collect(['old' => ['user_id' => $user2->id]]);

    $activities = new Collection([$activity1, $activity2]);

    $resolved = $resolver->resolve($activities);

    expect($resolved)->toHaveKey(UnitRelUser::class);
    expect($resolved[UnitRelUser::class])->toHaveCount(2);
    expect($resolved[UnitRelUser::class][$user1->id])->toBeInstanceOf(UnitRelUser::class);
});
