<?php

use App\Http\Controllers\Api\InquiryController;
use App\Http\Middleware\VerifyInquiryToken;
use Illuminate\Support\Facades\Route;

// Receives inquiries from WordPress (CF7). Protected by a shared secret token in the X-Inquiry-Token header.
Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware(VerifyInquiryToken::class);
