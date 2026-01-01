<?php

namespace Deifhelt\ActivityPresenter\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class RelationResolver
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Resolve related models for a collection of activities.
     * Use config['resolvers'] to determine which models to load.
     *
     * @param Collection<int, Activity>|EloquentCollection<int, Activity> $activities
     * @return array<string, \Illuminate\Database\Eloquent\Collection> Keyed by class name
     */
    public function resolve(Collection|EloquentCollection $activities): array
    {
        $idsToLoad = [];
        $resolvers = $this->config['resolvers'] ?? [];

        // 1. Scan activities to find IDs to load per model class
        foreach ($activities as $activity) {
            $properties = $activity->properties;
            $old = $properties['old'] ?? [];
            $attributes = $properties['attributes'] ?? [];

            // Merge keys to check both old and new values
            $allKeys = array_unique(array_merge(array_keys($old), array_keys($attributes)));

            foreach ($allKeys as $key) {
                if (!isset($resolvers[$key])) {
                    continue;
                }

                $modelClass = $resolvers[$key];

                // Check 'old' val
                if (isset($old[$key])) {
                    $idsToLoad[$modelClass][] = $old[$key];
                }

                // Check 'attributes' val
                if (isset($attributes[$key])) {
                    $idsToLoad[$modelClass][] = $attributes[$key];
                }
            }
        }

        // 2. Eager load models
        $loadedModels = [];
        foreach ($idsToLoad as $class => $ids) {
            $uniqueIds = array_unique(array_filter($ids)); // Filter nulls
            if (empty($uniqueIds)) {
                continue;
            }

            // We assume $class is an Eloquent Model
            if (class_exists($class)) {
                $loadedModels[$class] = $class::whereIn('id', $uniqueIds)->get()->keyBy('id');
            }
        }

        return $loadedModels;
    }
}
