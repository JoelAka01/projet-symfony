<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$envFile = dirname(__DIR__) . '/.env';
if (!is_file($envFile)) {
    $envFile = dirname(__DIR__) . '/.env.example';
}

(new Dotenv())->bootEnv($envFile);

if (filter_var($_SERVER['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL)) {
    umask(0000);
}
