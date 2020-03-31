<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'package'], function () {

    Route::get('/example', \Igaster\LaravelMetrics\Controllers\ExampleController::class.'@index');

});