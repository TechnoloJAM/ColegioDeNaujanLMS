<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified securely via Magic Link.
     */
    public function __invoke(Request $request, $id, $hash)
    {
        // 1. Find the user securely from the ID in the link
        $user = User::findOrFail($id);

        // 2. Verify the hash manually (Throws a 403 only if the link is actually fake/tampered)
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid or expired verification link.');
        }

        // 3. If they are already verified, just ensure they are logged in and proceed
        if ($user->hasVerifiedEmail()) {
            if (!Auth::check() || Auth::id() !== $user->id) {
                Auth::login($user);
            }
            return Inertia::render('Auth/VerifiedSuccess');
        }

        // 4. Mark as verified and fire system events
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // 5. Ensure they are logged in seamlessly
        if (!Auth::check() || Auth::id() !== $user->id) {
            Auth::login($user);
        }

        return Inertia::render('Auth/VerifiedSuccess');
    }
}