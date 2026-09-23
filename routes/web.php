<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('application');
});

Route::get('/access/{path?}', function () {
    return view('application');
})->where('path', '.*');
