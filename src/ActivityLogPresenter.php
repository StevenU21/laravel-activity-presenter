<?php

namespace Deifhelt\ActivityPresenter;

use Deifhelt\ActivityPresenter\Data\ActivityPresentationDTO;
use Deifhelt\ActivityPresenter\Data\AttributeChange;
use Deifhelt\ActivityPresenter\Services\RelationResolver;
use Deifhelt\ActivityPresenter\Services\TranslationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogPresenter
{
    protected RelationResolver $resolver;
    protected TranslationService $translator;
    protected array $config;

    public function __construct(
        RelationResolver $resolver,
        TranslationService $translator,
        array $config
    ) {
        $this->resolver = $resolver;
        $this->translator = $translator;
        $this->config = $config;
    }

    public function present(Activity $activity): ActivityPresentationDTO
    {
        $resolvedModels = $this->resolver->resolve(new Collection([$activity]));

        return $this->buildExhaustiveDto($activity, $resolvedModels);
    }

    public function presentCollection(Collection|EloquentCollection $activities): Collection
    {
        $resolvedModels = $this->resolver->resolve($activities);

        return $activities->map(function ($activity) use ($resolvedModels) {
            return $this->buildExhaustiveDto($activity, $resolvedModels);
        });
    }

    protected function buildExhaustiveDto(Activity $activity, array $resolvedModels): ActivityPresentationDTO
    {
        $properties = $activity->properties ?? collect([]);
        $old = $properties['old'] ?? [];
        $new = $properties['attributes'] ?? [];

        // Merge keys from both old and new to support deletions/creations equally
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        $changes = collect();
        $hidden = $this->config['hidden_attributes'] ?? [];
        $resolvers = $this->config['resolvers'] ?? [];

        foreach ($allKeys as $key) {
            if (in_array($key, $hidden)) {
                continue;
            }

            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            // Resolve related model if applicable
            $relatedModel = null;
            if (isset($resolvers[$key])) {
                $modelClass = $resolvers[$key];
                // Check both old and new values for resolution
                // Prioritize new value, fallback to old if we want to show what it WAS (e.g. project #123)
                // Actually, often we want the model corresponding to the ID.
                // If it changed from 1 to 2, which model do we pass?
                // Ideally we might want both, but AttributeChange has one 'relatedModel'.
                // If the user wants specific 'oldModel' and 'newModel', we might need to expand AttributeChange.
                // For now, let's resolve the 'Current' relevant model (usually the new one, or old if deleted)

                $idToResolve = $newValue ?: $oldValue;
                if ($idToResolve && isset($resolvedModels[$modelClass][$idToResolve])) {
                    $relatedModel = $resolvedModels[$modelClass][$idToResolve];
                }
            }

            $changes->push(new AttributeChange(
                key: $key,
                old: $oldValue,
            new: $newValue,
                relatedModel: $relatedModel
            ));
        }

        return new ActivityPresentationDTO(
            activity: $activity,
            causer: $activity->causer,
            subject: $activity->subject,
            changes: $changes,
            translator: $this->translator,
            config: $this->config
        );
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
        ?callable $afterFetch = null,
        ?callable $mapGroupRow = null
    ) {
        $paginator = $query->paginate($perPage)->withQueryString();

        $activityIds = $paginator->getCollection()->map(fn($item) => $item->{$latestIdColumn} ?? $item->id)->filter()->toArray();

        if (empty($activityIds)) {
            return $paginator;
        }

        $activities = $this->fetchGroupedActivities($activityIds, $loadRelations, $afterFetch);

        $this->hydrateGroupedPaginator($paginator, $activities, $latestIdColumn, $mapGroupRow);

        return $paginator;
    }

    protected function fetchGroupedActivities(array $ids, ?callable $loadRelations, ?callable $afterFetch): Collection
    {
        $activityQuery = Activity::whereIn('id', $ids);

        if ($loadRelations) {
            $loadRelations($activityQuery);
        } else {
            $activityQuery->with(['causer', 'subject']);
        }

        $activities = $activityQuery->get();

        if ($afterFetch) {
            $afterFetch($activities);
        }

        return $activities->keyBy('id');
    }

    protected function hydrateGroupedPaginator(
        $paginator,
        Collection $activities,
        string $latestIdColumn,
        ?callable $mapGroupRow = null
    ): void {
        $resolvedModels = $this->resolver->resolve($activities);

        $paginator->getCollection()->transform(function ($groupRow) use ($activities, $resolvedModels, $latestIdColumn, $mapGroupRow) {
            $id = $groupRow->{$latestIdColumn} ?? $groupRow->id;

            if (!isset($activities[$id])) {
                return $groupRow;
            }

            $activity = $activities[$id];

            // Use the new build method
            $presentation = $this->buildExhaustiveDto($activity, $resolvedModels);

            $groupRow->presentation = $presentation;
            $groupRow->encoded_subject_type = $this->encodeSubjectType($activity->subject_type ?? '');

            if ($mapGroupRow) {
                return $mapGroupRow($groupRow, $activity, $presentation);
            }

            return $groupRow;
        });
    }
}
