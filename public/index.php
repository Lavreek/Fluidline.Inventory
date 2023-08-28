<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '77.50.146.14'])) {
    return function (array $context) {
        return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    };
}
