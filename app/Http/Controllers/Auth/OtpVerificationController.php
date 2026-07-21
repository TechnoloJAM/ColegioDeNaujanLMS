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

        // Check if a fresh OTP was already sent within the last 90 seconds
        $recentOtp = OtpToken::where('email', $email)
            ->where('purpose', 'registration')
            ->where('created_at', '>=', \Carbon\Carbon::now()->subSeconds(90))
            ->first();

        // If no active OTP exists (e.g., they just logged in), generate and send one automatically
        if (!$recentOtp) {
            OtpToken::where('email', $email)->where('purpose', 'registration')->delete();
            
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            OtpToken::create([
                'email' => $email,
                'token' => $code,
                'purpose' => 'registration',
            ]);
            
            \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\OtpMail($code));
        }

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

        return back()->with('success', 'A new 90-second code has been sent.');
    }
}