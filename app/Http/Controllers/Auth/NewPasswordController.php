<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:6',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $otpRecord = \App\Models\OtpToken::where('email', $request->email)
            ->where('token', $request->token)
            ->where('purpose', 'password_reset')
            ->latest()
            ->first();

        if (!$otpRecord) return back()->withErrors(['token' => 'Invalid reset code.']);
        
        if (\Carbon\Carbon::now()->diffInSeconds($otpRecord->created_at) > 90) {
            return back()->withErrors(['token' => 'This code has expired. Please request a new one.']);
        }

        $user = \App\Models\User::where('email', $request->email)->first();
        $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
        $user->save();

        \App\Models\OtpToken::where('email', $request->email)->delete();

        return redirect()->route('login')->with('status', 'Password successfully reset.');
    }
}