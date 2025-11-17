<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Increase upload limits for large ZIP imports in local/dev environments.
// Note: These runtime settings are especially useful when using `php artisan serve`.
@ini_set('upload_max_filesize', '200M');
@ini_set('post_max_size', '220M');
@ini_set('max_file_uploads', '50');
@ini_set('memory_limit', '512M');
@ini_set('max_input_time', '300');
@ini_set('max_execution_time', '300');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
