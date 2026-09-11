<?php

use Illuminate\Support\Facades\Route;

Route::get('/test-buttons', function () {
    return view('courses.test_buttons');
})->name('test.buttons');
