<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role' => 'student',
        ]);

        // Generate the 60-second OTP
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        \App\Models\OtpToken::create([
            'email' => $user->email,
            'token' => $code,
            'purpose' => 'registration',
        ]);

        \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\OtpMail($code));

        // Pass the email to the session so the OTP screen knows who is verifying
        session()->put('otp_email', $user->email);

        return redirect()->route('verification.notice');
    }
}