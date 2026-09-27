<?php

namespace App\Bootstrap;

use Dotenv\Dotenv;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Env;

/**
 * Loads application environment files using the project's UTF-8 encoding contract.
 */
final class LoadUtf8EnvironmentVariables extends LoadEnvironmentVariables
{
    /**
     * Creates the environment loader with an explicit source encoding.
     *
     * PHP 8.5's built-in web server can fail Dotenv's automatic source encoding
     * detection even when the file is valid UTF-8.
     */
    protected function createDotenv($app)
    {
        return Dotenv::create(
            Env::getRepository(),
            $app->environmentPath(),
            $app->environmentFile(),
            true,
            'UTF-8'
        );
    }
}
