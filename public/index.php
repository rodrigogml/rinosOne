<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Dotenv delegates source conversion to mbstring during the earliest bootstrap
// phase. Declare the application encoding before Composer/Laravel can read an
// environment file, including in long-lived PHP built-in server processes.
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
