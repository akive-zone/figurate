<?php

return [
    'driver' => 'stancl/tenancy',
    'database_strategy' => 'separate',
    'separate_database' => true,
    'identifiers' => ['domain', 'subdomain'],
];
