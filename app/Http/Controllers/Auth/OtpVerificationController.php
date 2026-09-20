<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OtpToken;
use App\Models\User;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Verified;
use Inertia\Inertia;
use Carbon\Carbon;

class OtpVerificationController extends Controller
{
    public function showRegistrationOtp(Request $request)
    {
        // Get email from session (new registration) OR logged-in user (login attempt)
        $email = session('otp_email') ?? $request->user()?->email;

        if (!$email) {
            return redirect()->route('login');
        }

        // Removed the auto-generate block here to prevent double-sending on page load. 
        // We now wait for the user to explicitly click the "Send Verification Code" button.

        return \Inertia\Inertia::render('Auth/VerifyOtp', ['email' => $email]);
    }

    public function verifyRegistration(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:6',
        ]);

        $otpRecord = OtpToken::where('email', $request->email)
            ->where('token', $request->token)
            ->where('purpose', 'registration')
            ->latest()
            ->first();

        if (!$otpRecord) {
            return back()->withErrors(['token' => 'Invalid verification code.']);
        }

        if (\Carbon\Carbon::now()->diffInSeconds($otpRecord->created_at) > 90) {
            return back()->withErrors(['token' => 'This code has expired. Please request a new one.']);
        }

        // Success! Verify and Login if not already logged in
        $user = User::where('email', $request->email)->first();

        if ($user) {
            $user->email_verified_at = now();
            $user->save();
            
            event(new Verified($user));
            
            if (!Auth::check()) {
                Auth::login($user);
            }
        }

        OtpToken::where('email', $request->email)->delete();

        return redirect()->route('dashboard');
    }

    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'purpose' => 'required|string'
        ]);

        OtpToken::where('email', $request->email)->where('purpose', $request->purpose)->delete();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        OtpToken::create([
            'email' => $request->email,
            'token' => $code,
            'purpose' => $request->purpose,
        ]);

        Mail::to($request->email)->send(new OtpMail($code));

        return back()->with('success', 'A 6-digit code has been sent to your email.');
    }
}