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

        if (isset($aliases[$modelClass])) {
            return $aliases[$modelClass];
        }
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($modelClass));
    }

    /**
     * Decode a URL parameter back to a model class name.
     */
    public function decodeSubjectType(string $param): string
    {
        $aliases = $this->config['subject_aliases'] ?? [];

        $class = array_search($param, $aliases);
        if ($class !== false) {
            return $class;
        }

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
    public function presentGrouped(
        Builder|QueryBuilder $query,
        int $perPage = 10,
        string $latestIdColumn = 'latest_id',
        ?callable $loadRelations = null,
        ?callable $afterFetch = null
    ) {
        $paginator = $query->paginate($perPage)->withQueryString();

        $activityIds = $paginator->getCollection()->map(fn($item) => $item->{$latestIdColumn} ?? $item->id)->filter()->toArray();

        if (empty($activityIds)) {
            return $paginator;
        }

        $activityQuery = Activity::whereIn('id', $activityIds);

        if ($loadRelations) {
            $loadRelations($activityQuery);
        } else {
            $activityQuery->with(['causer', 'subject']);
        }

        $activities = $activityQuery->get();

        if ($afterFetch) {
            $afterFetch($activities);
        }

        $activities = $activities->keyBy('id');
        $resolvedModels = $this->resolver->resolve($activities);
        $paginator->getCollection()->transform(function ($groupRow) use ($activities, $resolvedModels, $latestIdColumn) {
            $id = $groupRow->{$latestIdColumn} ?? $groupRow->id;

            if (!isset($activities[$id])) {
                return $groupRow;
            }

            $activity = $activities[$id];
            $presentation = ActivityPresentationDTO::fromActivity($activity, $resolvedModels, $this->config);

            $groupRow->presentation = $presentation;
            $groupRow->encoded_subject_type = $this->encodeSubjectType($activity->subject_type ?? '');

            return $groupRow;
        });

        return $paginator;
    }
}
