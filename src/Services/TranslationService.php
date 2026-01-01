<?php

namespace Deifhelt\ActivityPresenter\Services;

use Illuminate\Support\Facades\Lang;

class TranslationService
{
    public function translateEvent(string $eventName): string
    {
        $key = "activity-presenter::logs.events.{$eventName}";

        if (Lang::has($key)) {
            return trans($key);
        }

        return ucfirst($eventName);
    }

    public function translateModel(string $modelClass): string
    {
        $basename = class_basename($modelClass);
        $key = "activity-presenter::logs.models.{$basename}";

        if (Lang::has($key)) {
            return trans($key);
        }

        return $basename;
    }

    public function translateAttribute(string $attribute): string
    {
        $key = "activity-presenter::logs.attributes.{$attribute}";

        if (Lang::has($key)) {
            return trans($key);
        }

        return str_replace('_', ' ', ucfirst($attribute));
    }
}
