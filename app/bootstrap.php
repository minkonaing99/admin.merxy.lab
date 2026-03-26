<?php

declare(strict_types=1);

if (!defined('MERXYLAB_APP_BOOTSTRAPPED')) {
    define('MERXYLAB_APP_BOOTSTRAPPED', true);
    define('MERXYLAB_ROOT', dirname(__DIR__));

    require_once __DIR__ . '/core/Config.php';
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Http.php';
    require_once __DIR__ . '/core/ResponseCache.php';

    MerxyLab\Core\Config::boot(MERXYLAB_ROOT . DIRECTORY_SEPARATOR . '.env');
}
