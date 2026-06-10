<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

// extends Symfony's base kernel to support a custom cache directory
 // via the APP_CACHE_DIR env variable (falls back to symfony default).
//cache is stored in APP_CACHE_DIR/{environment} when set.

    public function getCacheDir(): string
    {
        $cacheDir = $_SERVER['APP_CACHE_DIR'] ?? $_ENV['APP_CACHE_DIR'] ?? null;
        if (is_string($cacheDir) && '' !== $cacheDir) {
            return rtrim($cacheDir, '/\\').'/'.$this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        $logDir = $_SERVER['APP_LOG_DIR'] ?? $_ENV['APP_LOG_DIR'] ?? null;
        if (is_string($logDir) && '' !== $logDir) {
            return rtrim($logDir, '/\\');
        }

        return parent::getLogDir();
    }
}
