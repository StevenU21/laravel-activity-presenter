<?php

namespace Deifhelt\ActivityPresenter;

use Deifhelt\ActivityPresenter\Data\ActivityPresentationDTO;
use Deifhelt\ActivityPresenter\Services\RelationResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPresenter
{
    protected RelationResolver $resolver;
    protected array $config;

    public function __construct(RelationResolver $resolver, array $config)
    {
        $this->resolver = $resolver;
        $this->config = $config;
    }

    public function present(Activity $activity): ActivityPresentationDTO
    {
        $resolvedModels = $this->resolver->resolve(new Collection([$activity]));

        return ActivityPresentationDTO::fromActivity($activity, $resolvedModels, $this->config);
    }

    public function presentCollection(Collection|EloquentCollection $activities): Collection
    {
        $resolvedModels = $this->resolver->resolve($activities);

        return $activities->map(function ($activity) use ($resolvedModels) {
            return ActivityPresentationDTO::fromActivity($activity, $resolvedModels, $this->config);
        });
    }
}
