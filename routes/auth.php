<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    // Password reset stays in 'guest' because logged-in users shouldn't access the forgot password flow
    Route::get('reset-password', function() {
        if (!session('reset_email')) return redirect()->route('password.request');
        return \Inertia\Inertia::render('Auth/ResetPassword', ['email' => session('reset_email')]);
    })->name('password.reset.otp');
    
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::get('verify-otp', [App\Http\Controllers\Auth\OtpVerificationController::class, 'showRegistrationOtp'])
    ->name('verification.notice'); 

Route::post('verify-otp', [App\Http\Controllers\Auth\OtpVerificationController::class, 'verifyRegistration'])
    ->name('verification.verify_otp');
    
Route::post('resend-otp', [App\Http\Controllers\Auth\OtpVerificationController::class, 'resend'])
    ->name('otp.resend');

// 🌟 RESTORED AUTH ROUTES (Logout & Profile Password Update)
Route::middleware('auth')->group(function () {
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});