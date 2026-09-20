<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
        $driver = Socialite::driver('google');
        return $driver->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback()
    {
        try {
            // FIXED 1: Added stateless() to prevent the "have to click twice" session bug
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => null, 
                    'role' => 'student', 
                    'email_verified_at' => null, 
                ]);
                
                // FIXED 2: Auto-send OTP logic removed. Now handled by the user clicking "Send Code" on the Verify page.
                session()->put('otp_email', $user->email);
                
                event(new Registered($user));
                return redirect()->route('verification.notice');
            } else {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]);
            }

            if ($user->status === 'suspended') {
                return redirect()->route('login')->withErrors([
                    'email' => 'SUSPENDED: ' . ($user->suspension_reason ?? 'Your account has been suspended.')
                ]);
            }

            if (!$user->hasVerifiedEmail()) {
                // FIXED 3: Auto-send OTP logic removed here as well.
                session()->put('otp_email', $user->email);
                return redirect()->route('verification.notice');
            }

            Auth::login($user);

            if (empty($user->school_id)) {
                return redirect()->route('register.onboarding');
            }

            return redirect()->intended('/dashboard');

        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Google Login Failed');
        }
    }
    
    public function onboarding()
    {
        return Inertia::render('Auth/Onboarding', [
            'user' => Auth::user()
        ]);
    }

    public function completeRegistration(Request $request)
    {
        $request->validate([
            'school_id' => 'required|string|max:50',
            'program' => 'required|string|max:100',
            'contact_number' => 'required|digits:11',
            'password' => [
                'required', 
                'string', 
                'min:8', 
                'regex:/[0-9]/',
                'confirmed'
            ],
            'terms' => 'accepted',
        ], [
            'contact_number.digits' => 'Your contact number must be exactly 11 digits.',
            'password.regex' => 'Your password must contain at least one number.',
        ]);

        $user = User::find(Auth::id());
        
        $user->update([
            'school_id' => $request->school_id,
            'program' => $request->program, 
            'contact_number' => $request->contact_number,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('dashboard');
    }
}