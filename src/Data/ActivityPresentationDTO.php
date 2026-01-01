<?php

namespace Deifhelt\ActivityPresenter\Data;

use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Str;

class ActivityPresentationDTO
{
    public function __construct(
        public int $id,
        public string $date,
        public string $diff, // Human readable time diff
        public string $user_name,
        public string $event,
        public string $subject_type,
        public string $subject_name,
        public array $old_values,
        public array $new_values,
        public array $raw_properties,
    ) {
    }

    public static function fromActivity(Activity $activity, array $resolvedModels, array $config): self
    {
        $labelAttributes = $config['label_attribute'] ?? [];
        $resolvers = $config['resolvers'] ?? [];
        $hiddenAttributes = $config['hidden_attributes'] ?? [];

        // 1. Resolve User Name
        $causer = $activity->causer;
        $userName = 'System';
        if ($causer) {
            $userClass = get_class($causer);
            $userLabelAttr = $labelAttributes[$userClass] ?? 'name'; // Default to name
            $userName = $causer->{$userLabelAttr} ?? 'Unknown';
        }

        // 2. Resolve Subject Name
        $subjectName = 'Unknown Entity';
        if ($activity->subject) {
            $subjectClass = get_class($activity->subject);
            $subjectLabelAttr = $labelAttributes[$subjectClass] ?? 'title'; // Default assumption
            if (isset($activity->subject->{$subjectLabelAttr})) {
                $subjectName = $activity->subject->{$subjectLabelAttr};
            } else {
                $subjectName = class_basename($subjectClass) . " #{$activity->subject_id}";
            }
        } elseif ($activity->subject_type) {
            // Subject deleted?
            $subjectName = class_basename($activity->subject_type) . " #{$activity->subject_id}";
        }

        // 3. Process Properties (Values)
        $properties = $activity->properties;
        $old = $properties['old'] ?? [];
        $attributes = $properties['attributes'] ?? [];

        $processValues = function (array $values) use ($resolvers, $resolvedModels, $labelAttributes, $hiddenAttributes) {
            $processed = [];
            foreach ($values as $key => $value) {
                if (in_array($key, $hiddenAttributes))
                    continue;

                if (isset($resolvers[$key])) {
                    $modelClass = $resolvers[$key];
                    // Look up in resolved cache
                    if (isset($resolvedModels[$modelClass][$value])) {
                        $model = $resolvedModels[$modelClass][$value];
                        $labelAttr = $labelAttributes[$modelClass] ?? 'name';
                        $processed[$key] = $model->{$labelAttr} ?? $value;
                        continue;
                    }
                }
                $processed[$key] = $value;
            }
            return $processed;
        };

        return new self(
            id: $activity->id,
            date: $activity->created_at->format('Y-m-d H:i:s'),
            diff: $activity->created_at->diffForHumans(),
            user_name: $userName,
            event: $activity->event, // Can be translated later or here if passed translator
            subject_type: class_basename($activity->subject_type),
            subject_name: $subjectName,
            old_values: $processValues($old),
            new_values: $processValues($attributes),
            raw_properties: $properties->toArray()
        );
    }
}
