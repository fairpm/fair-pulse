<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    echo "::error::Project autoloader not found at {$autoloadPath}\n";
    exit(1);
}

require_once $autoloadPath;
