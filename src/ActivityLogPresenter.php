<?php

namespace Deifhelt\ActivityPresenter;

use Deifhelt\ActivityPresenter\Data\ActivityPresentationDTO;
use Deifhelt\ActivityPresenter\Services\RelationResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * Encode a model class name for use in URLs.
     * Uses 'subject_aliases' config if available, otherwise Base64.
     */
    public function encodeSubjectType(string $modelClass): string
    {
        $aliases = $this->config['subject_aliases'] ?? [];

        // Check for alias (Value -> Key)
        // Check if $modelClass is a key in aliases
        if (isset($aliases[$modelClass])) {
            return $aliases[$modelClass];
        }

        // Search if $modelClass is a value in aliases (if user mapped 'alias' => Class)
        // Standard convention is usually Class => Alias or Alias => Class. 
        // Let's assume Key=Class, Value=Alias based on standard laravel morph map 
        // BUT user said: 'subject_aliases' => [Sale::class => 'sale']
        if (isset($aliases[$modelClass])) {
            return $aliases[$modelClass];
        }

        // Fallback: URL safe Base64
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($modelClass));
    }

    /**
     * Decode a URL parameter back to a model class name.
     */
    public function decodeSubjectType(string $param): string
    {
        $aliases = $this->config['subject_aliases'] ?? [];

        // Check if param is an alias (search for value)
        $class = array_search($param, $aliases);
        if ($class !== false) {
            return $class;
        }

        // Fallback: Base64 decode
        $base64 = str_replace(['-', '_'], ['+', '/'], $param);
        return base64_decode($base64);
    }

    /**
     * Present a grouped query (Index Pattern).
     * Expects a query grouped by subject_type and subject_id.
     * It efficiently resolves the LATEST activity for each group.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function presentGrouped(Builder|QueryBuilder $query, int $perPage = 10)
    {
        // 1. Paginate the grouped results (lightweight, just subject_id/type/max_id)
        $paginator = $query->paginate($perPage);

        // 2. Extract the actual Activity IDs from the "latest" aggregate
        // Assuming the query selects MAX(id) as latest_id or similar
        // If not, we might need a convention. Let's assume the user selects 'max(id) as id' or just 'id'.
        // Actually best practice is to require the user to pass a builder that selects the ID.

        // Let's iterate the items. Each item is likely a generic object or partial Activity model
        $activityIds = $paginator->getCollection()->map(fn($item) => $item->latest_id ?? $item->id)->filter()->toArray();

        if (empty($activityIds)) {
            return $paginator; // Return empty paginator
        }

        // 3. Fetch full Activity models
        $activities = Activity::whereIn('id', $activityIds)
            ->with(['causer', 'subject']) // Eager load basics
            ->get()
            ->keyBy('id');

        // 4. Resolve Presentation (Relation Resolver)
        $resolvedModels = $this->resolver->resolve($activities);

        // 5. Transform the Paginator Collection
        $paginator->getCollection()->transform(function ($groupRow) use ($activities, $resolvedModels) {
            $id = $groupRow->latest_id ?? $groupRow->id;

            if (!isset($activities[$id])) {
                return $groupRow; // Should not happen
            }

            $activity = $activities[$id];

            // Present the activity
            $presentation = ActivityPresentationDTO::fromActivity($activity, $resolvedModels, $this->config);

            // Hydrate/Attach presentation to the row (or return generic object)
            // User suggestion: "hydrate($activities, into: 'activity')"
            // Here we are inside the method, so we can just return a rich object.
            // Let's attach to the generic row.
            $groupRow->presentation = $presentation;

            return $groupRow;
        });

        return $paginator;
    }
}
