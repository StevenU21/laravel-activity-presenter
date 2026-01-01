<?php

namespace Deifhelt\ActivityPresenter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Deifhelt\ActivityPresenter\ActivityLogPresenter
 */
class ActivityPresenter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'activity-presenter';
    }
}
