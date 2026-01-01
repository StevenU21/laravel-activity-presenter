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

    /**
     * Present a single activity.
     * Note: This checks for N+1 issues if relations aren't loaded, 
     * but handles them gracefully for single item.
     *
     * @param Activity $activity
     * @return ActivityPresentationDTO
     */
    public function present(Activity $activity): ActivityPresentationDTO
    {
        // For single item, we can just resolve for a collection of one, 
        // essentially satisfying the DTO factory requirement.
        $resolvedModels = $this->resolver->resolve(new Collection([$activity]));

        return ActivityPresentationDTO::fromActivity($activity, $resolvedModels, $this->config);
    }

    /**
     * Present a collection of activities.
     * Efficiently preloads necessary data.
     *
     * @param Collection<int, Activity>|EloquentCollection<int, Activity> $activities
     * @return Collection<int, ActivityPresentationDTO>
     */
    public function presentCollection(Collection|EloquentCollection $activities): Collection
    {
        // 1. Resolve all related models to avoid N+1
        $resolvedModels = $this->resolver->resolve($activities);

        // 2. Map to DTOs
        return $activities->map(function ($activity) use ($resolvedModels) {
            return ActivityPresentationDTO::fromActivity($activity, $resolvedModels, $this->config);
        });
    }
}
