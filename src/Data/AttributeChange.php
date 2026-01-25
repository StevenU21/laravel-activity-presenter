<?php

namespace Deifhelt\ActivityPresenter\Data;

use Illuminate\Database\Eloquent\Model;

class AttributeChange
{
    public function __construct(
        public string $key,
        public mixed $old,
        public mixed $new,
        public ?Model $relatedModel = null,
    ) {
    }
}
