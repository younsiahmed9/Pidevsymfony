<?php

use App\Kernel;

// Réduit les blocages SMTP/HTTP longs (PHP max_execution_time par défaut souvent 30s sous Apache)
if (\PHP_SAPI !== 'cli') {
    ini_set('max_execution_time', '120');
    ini_set('default_socket_timeout', '20');
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
