<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        \App\Models\OtpToken::where('email', $request->email)->where('purpose', 'password_reset')->delete();
        
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        \App\Models\OtpToken::create([
            'email' => $request->email,
            'token' => $code,
            'purpose' => 'password_reset',
        ]);

        \Illuminate\Support\Facades\Mail::to($request->email)->send(new \App\Mail\OtpMail($code));

        session()->put('reset_email', $request->email);

        return redirect()->route('password.reset.otp');
    }
}
