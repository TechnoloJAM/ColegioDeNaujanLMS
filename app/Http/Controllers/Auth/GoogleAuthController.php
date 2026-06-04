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
            $googleUser = Socialite::driver('google')->user();
            
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(), // Pulls Google Avatar automatically
                    'password' => null, 
                    'role' => 'student', 
                    'email_verified_at' => null, 
                ]);
                
                event(new Registered($user));
            } else {
                // Keep avatar synced to their current Google picture every time they log in
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

            Auth::login($user);

            if (!$user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

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
            'contact_number' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'terms' => 'accepted',
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