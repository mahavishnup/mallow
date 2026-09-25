<?php

declare(strict_types=1);

return [
    'ingestion' => [
        'merchant_per_minute' => 600,
        'customer_per_minute' => 60,
    ],

    'aggregation' => [
        'chunk_size' => 1000,
    ],

    'dashboard' => [
        'projection'           => 'linear',
        'churn_drop_threshold' => 0.5,
    ],

    'cache' => [
        'plan_pricing_ttl' => 86400,
    ],
];
