<?php

declare(strict_types=1);

use App\Infrastructure\Database\DatabaseConfig;

$config = DatabaseConfig::fromEnvironment();

return [
    'default' => $config->getDriver(),
    'connections' => [
        'mysql' => $config->toArray(),
    ],
];
