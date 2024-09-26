<?php

use Illuminate\Support\Facades\Route;
use Spark\Http\Middleware\HandleInertiaRequests;

Route::group(['namespace' => 'Spark\Http\Controllers'], function () {
    Route::group([
        'prefix' => 'spark',
        'middleware' => config('spark.middleware', ['web', 'auth']),
    ], function () {
        // Subscription...
        Route::post('/subscription', 'NewSubscriptionController');
        Route::put('/subscription', 'UpdateSubscriptionController');
        Route::put('/subscription/cancel', 'CancelSubscriptionController');
        Route::put('/subscription/resume', 'ResumeSubscriptionController');

        // Payment Method...
        Route::put('/subscription/payment-method', 'UpdatePaymentMethodController');

        // Pending Checkouts...
        Route::post('/pending-checkout', 'NewPendingCheckoutController');

        // Invoice PDF...
        Route::get('/{type}/{id}/invoices/{transaction}/download', 'DownloadInvoiceController')
            ->name('spark.invoices.download');
    });

    Route::group([
        'prefix' => config('spark.path'),
        'middleware' => array_merge(config('spark.middleware', ['web', 'auth']), [HandleInertiaRequests::class]),
    ], function () {
        Route::get('/{type?}/{id?}', 'BillingPortalController')->name('spark.portal');
    });
});
