<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (is_string($path)) {
    $file = __DIR__.'/../public'.$path;
    if (is_file($file)) {
        return false;
    }
}

require __DIR__.'/../public/index.php';
