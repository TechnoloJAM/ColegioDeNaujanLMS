<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Customize the default Laravel Verification Email
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verify Your Colegio de Naujan Account')
                ->greeting('Hello ' . $notifiable->name . '!')
                ->line('An account has been created for you in the Colegio de Naujan LMS.')
                ->line('Please click the button below to verify your email address and activate your account.')
                ->action('Verify My Account', $url)
                ->line('If you did not request this account, you can safely ignore this email.');
        });
        Schema::defaultStringLength(191);
    }
}