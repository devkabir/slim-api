<?php

declare(strict_types=1);

use App\App;

/*
|--------------------------------------------------------------------------
| Create and Bootstrap The Application
|--------------------------------------------------------------------------
|
| This file initializes runtime error handling, loads environment variables,
| creates the dependency injection container, registers global middleware,
| and maps application routes before returning the Slim application instance.
|
*/

return App::bootstrap();
