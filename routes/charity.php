<?php

use App\Http\Controllers\CharityMagicLoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('lief-en-leed')->name('charity.')->controller(CharityMagicLoginController::class)->group(function () {
    Route::get('login', 'show')->name('login');
    Route::post('login', 'sendLink')->middleware('throttle:4,1')->name('magic.send');
    Route::get('magic-login', 'verify')->middleware(['throttle:10,1', 'signed'])->name('magic.verify');
    Route::post('logout', 'logout')->name('logout');
});
