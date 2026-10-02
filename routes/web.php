<?php

use Illuminate\Support\Facades\Route;

if (app()->environment('staging')) {
    Route::get('/dev', static fn () => response('Oi mundo'));
}

Route::get('/', function () {
    return view('application');
});

Route::get('/access/{path?}', function () {
    return view('application');
})->where('path', '.*');
