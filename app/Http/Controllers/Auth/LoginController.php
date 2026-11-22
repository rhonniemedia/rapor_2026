<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Rapor\RaporAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Login with the shared accounts (same rules as PINTAR: e-mail, NIP or username).
 * Differences: every role of this app may log in (admin, superadmin AND user = teachers), failed
 * attempts are rate limited, and "remember me" does not exist (it would write to the shared users table).
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 60;

    public function showLoginForm()
    {
        return view('pages.auth.login');
    }

    public function authenticate(Request $request, RaporAccess $access)
    {
        $request->validate([
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginId = trim($request->input('login_id'));
        $throttleKey = Str::lower($loginId) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'login_id' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
            ])->withInput($request->only('login_id'));
        }

        $user = $this->findUser($loginId);

        if ($user && Hash::check($request->input('password'), $user->password)) {
            if (! $access->hasAccess($user)) {
                return back()->withErrors([
                    'login_id' => 'Akun Anda valid, tetapi tidak memiliki otoritas untuk mengakses aplikasi ini.',
                ])->withInput($request->only('login_id'));
            }

            RateLimiter::clear($throttleKey);
            Auth::login($user); // no "remember me": nothing is written to the users table
            $request->session()->regenerate();

            return redirect()->intended(route('home'));
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        return back()->withErrors([
            'login_id' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
        ])->withInput($request->only('login_id'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** E-mail and NIP are matched on their SHA-256 hashes in the staff vault; anything else is a username. */
    private function findUser(string $loginId): ?User
    {
        if (filter_var($loginId, FILTER_VALIDATE_EMAIL)) {
            $hash = hash('sha256', strtolower($loginId));

            return User::whereHas('staff.vault', fn($query) => $query->where('email_hash', $hash))->first();
        }

        if (preg_match('/^[0-9]{10,20}$/', $loginId)) {
            $hash = hash('sha256', $loginId);

            return User::whereHas('staff.vault', fn($query) => $query->where('nip_hash', $hash))->first();
        }

        return User::where('username', $loginId)->first();
    }
}
