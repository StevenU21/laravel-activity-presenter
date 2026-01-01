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

    public function resolve(Collection|EloquentCollection $activities): array
    {
        $idsToLoad = [];
        $resolvers = $this->config['resolvers'] ?? [];

        foreach ($activities as $activity) {
            $properties = $activity->properties;
            $old = $properties['old'] ?? [];
            $attributes = $properties['attributes'] ?? [];

            $allKeys = array_unique(array_merge(array_keys($old), array_keys($attributes)));

            foreach ($allKeys as $key) {
                if (!isset($resolvers[$key])) {
                    continue;
                }

                $modelClass = $resolvers[$key];

                if (isset($old[$key])) {
                    $idsToLoad[$modelClass][] = $old[$key];
                }

                if (isset($attributes[$key])) {
                    $idsToLoad[$modelClass][] = $attributes[$key];
                }
            }
        }

        $loadedModels = [];
        foreach ($idsToLoad as $class => $ids) {
            $uniqueIds = array_unique(array_filter($ids));
            if (empty($uniqueIds)) {
                continue;
            }

            if (class_exists($class)) {
                $loadedModels[$class] = $class::whereIn('id', $uniqueIds)->get()->keyBy('id');
            }
        }

        return $loadedModels;
    }
}
