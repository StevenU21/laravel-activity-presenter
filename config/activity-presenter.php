<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Resolvers
    |--------------------------------------------------------------------------
    |
    | Define the mapping between activity log fields and their corresponding
    | Eloquent models. This allows the package to automatically preload
    | related models.
    |
    */
    'resolvers' => [
        // 'user_id' => \App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Label Attributes
    |--------------------------------------------------------------------------
    |
    | Specify which attribute should be used as the display label for each
    | resolved model. You can use standard attributes or accessors.
    |
    */
    'label_attribute' => [
        // \App\Models\User::class => 'name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden Attributes
    |--------------------------------------------------------------------------
    |
    | Attributes that should be excluded from the presentation DTOs, specifically
    | useful for diff views.
    |
    */
    'hidden_attributes' => [
        'password',
        'remember_token',
        'updated_at',
        'created_at',
        'deleted_at',
    ],
];
