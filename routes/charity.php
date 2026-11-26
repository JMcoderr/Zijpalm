<?php

use App\Http\Controllers\CharityMagicLoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('lief-en-leed')->name('charity.')->controller(CharityMagicLoginController::class)->group(function () {
    Route::get('login', 'show')->name('login');
    Route::post('login', 'sendLink')->middleware('throttle:4,1,charity-send:')->name('magic.send');
    Route::get('magic-login', 'confirm')->middleware(['throttle:10,1,charity-preview:', 'signed'])->name('magic.verify');
    Route::post('magic-login', 'verify')->middleware(['throttle:10,1,charity-verify:', 'signed']);
    Route::post('logout', 'logout')->name('logout');
});
