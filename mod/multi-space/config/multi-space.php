<?php

return [
    'driver' => 'spatie/laravel-multitenancy',
    'database_strategy' => 'shared',
    'shared_database' => true,
    'requires_site_context' => true,
    'parent_layer' => 'multi_site',
    'scope_columns' => ['site_id', 'space_id'],
];
