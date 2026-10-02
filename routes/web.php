<?php

use Illuminate\Support\Facades\Route;

if (app()->environment('staging')) {
    Route::get('/dev', static fn () => view('developer-guide'));
}

Route::get('/', function () {
    return view('application');
});

Route::get('/access/{path?}', function () {
    return view('application');
})->where('path', '.*');
