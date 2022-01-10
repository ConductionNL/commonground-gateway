<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

/* @todo remove hotfix for execution time */
ini_set('max_execution_time', 300);

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
