<?php

namespace Deifhelt\ActivityPresenter\Data;

use Deifhelt\ActivityPresenter\Services\TranslationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityPresentationDTO
{
    /**
     * @param  Collection<int, AttributeChange>  $changes
     */
    public function __construct(
        public readonly Activity $activity,
        public readonly ?Model $causer,
        public readonly ?Model $subject,
        public readonly Collection $changes,
        protected readonly TranslationService $translator,
        protected readonly array $config = []
    ) {
    }

    public function getCauserLabel(?string $attribute = null): string
    {
        if (!$this->causer) {
            return 'System';
        }

        $attribute = $attribute ?? $this->config['label_attribute'][get_class($this->causer)] ?? 'name';

        return $this->causer->getAttribute($attribute) ?? 'Unknown';
    }

    public function getSubjectLabel(?string $attribute = null): string
    {
        if ($this->subject) {
            $class = get_class($this->subject);
            $attribute = $attribute ?? $this->config['label_attribute'][$class] ?? null;

            if ($attribute) {
                return $this->subject->getAttribute($attribute) ?? (string) $this->subject->getKey();
            }

            // Fallback to standard attributes if no config
            foreach (['audit_display', 'name', 'title', 'code', 'slug', 'search_label'] as $guess) {
                if ($val = $this->subject->getAttribute($guess)) {
                    return $val;
                }
            }

            return $this->translator->translateModel($class) . " #{$this->subject->getKey()}";
        }

        if ($this->activity->subject_type) {
            return $this->translator->translateModel($this->activity->subject_type) . " #{$this->activity->subject_id}";
        }

        return 'Unknown Entity';
    }

    public function getEventLabel(): string
    {
        return $this->translator->translateEvent($this->activity->event);
    }
}
