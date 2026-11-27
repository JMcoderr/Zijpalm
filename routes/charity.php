<?php

use App\Http\Controllers\CharityMagicLoginController;
use App\Http\Controllers\CharityRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('lief-en-leed')->name('charity.')->controller(CharityMagicLoginController::class)->group(function () {
    Route::get('login', 'show')->name('login');
    Route::post('login', 'sendLink')->middleware('throttle:4,1,charity-send:')->name('magic.send');
    Route::get('magic-login', 'confirm')->middleware(['throttle:10,1,charity-preview:', 'signed'])->name('magic.verify');
    Route::post('magic-login', 'verify')->middleware(['throttle:10,1,charity-verify:', 'signed']);
    Route::post('logout', 'logout')->name('logout');

    Route::get('aanvragen', [CharityRequestController::class, 'create'])
        ->middleware('charity.session')
        ->name('requests.create');

    Route::post('aanvragen', [CharityRequestController::class, 'store'])
        ->middleware('charity.session')
        ->name('requests.store');

    Route::middleware('charity.admin')->group(function () {
        Route::get('aanvragen/beheer', [CharityRequestController::class, 'index'])
            ->name('requests.review');

        Route::patch('aanvragen/{charityRequest}/beoordeling', [CharityRequestController::class, 'update'])
            ->name('requests.review.update');
    });

    Route::get('aanvragen/betaaloverzicht', [CharityRequestController::class, 'paymentOverview'])
        ->middleware('charity.super')
        ->name('requests.payment-overview');

    Route::post('aanvragen/betaaloverzicht/gegevens', [CharityRequestController::class, 'storePaymentDebtorDetails'])
        ->middleware('charity.super')
        ->name('requests.payment-details');
});
