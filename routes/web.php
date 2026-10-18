<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

if (config('ulvi.demo_enabled')) {
    Route::get('/demo/checkout/{session}', [DemoController::class, 'checkout'])->name('demo.checkout.show');
    Route::post('/demo/checkout/{session}/pay', [DemoController::class, 'pay'])->name('demo.checkout.pay');
}
