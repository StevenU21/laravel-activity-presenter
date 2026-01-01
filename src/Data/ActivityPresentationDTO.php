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
        $translator = app(\Deifhelt\ActivityPresenter\Services\TranslationService::class);

        $userName = self::resolveUserName($activity, $labelAttributes);
        $subjectName = self::resolveSubjectName($activity, $labelAttributes, $translator);

        return new self(
            id: $activity->id,
            date: $activity->created_at->format('Y-m-d H:i:s'),
            diff: $activity->created_at->diffForHumans(),
            user_name: $userName,
            event: $translator->translateEvent($activity->event),
            subject_type: $translator->translateModel($activity->subject_type ?? ''),
            subject_name: $subjectName,
            old_values: self::resolveValues($activity->properties['old'] ?? [], $config, $resolvedModels, $translator),
            new_values: self::resolveValues($activity->properties['attributes'] ?? [], $config, $resolvedModels, $translator),
            raw_properties: $activity->properties->toArray()
        );
    }

    private static function resolveUserName(Activity $activity, array $labelAttributes): string
    {
        $causer = $activity->causer;
        if (!$causer) {
            return 'System';
        }

        $userClass = get_class($causer);
        $userLabelAttr = $labelAttributes[$userClass] ?? 'name';

        return $causer->{$userLabelAttr} ?? 'Unknown';
    }

    private static function resolveSubjectName(Activity $activity, array $labelAttributes, $translator): string
    {
        if ($activity->subject) {
            $subjectClass = get_class($activity->subject);
            $subjectLabelAttr = $labelAttributes[$subjectClass] ?? 'title';

            return $activity->subject->{$subjectLabelAttr}
                ?? $translator->translateModel($subjectClass) . " #{$activity->subject_id}";
        }

        if ($activity->subject_type) {
            return $translator->translateModel($activity->subject_type) . " #{$activity->subject_id}";
        }

        return 'Unknown Entity';
    }

    private static function resolveValues(array $values, array $config, array $resolvedModels, $translator): array
    {
        $processed = [];
        $resolvers = $config['resolvers'] ?? [];
        $hiddenAttributes = $config['hidden_attributes'] ?? [];
        $labelAttributes = $config['label_attribute'] ?? [];

        foreach ($values as $key => $value) {
            if (in_array($key, $hiddenAttributes)) {
                continue;
            }

            $translatedKey = $translator->translateAttribute($key);

            if (isset($resolvers[$key])) {
                $modelClass = $resolvers[$key];
                if (isset($resolvedModels[$modelClass][$value])) {
                    $model = $resolvedModels[$modelClass][$value];
                    $labelAttr = $labelAttributes[$modelClass] ?? 'name';
                    $processed[$translatedKey] = $model->{$labelAttr} ?? $value;
                    continue;
                }
            }

            $processed[$translatedKey] = $value;
        }

        return $processed;
    }
}
