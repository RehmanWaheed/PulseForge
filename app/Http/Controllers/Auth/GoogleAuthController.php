<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\TenantRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): RedirectResponse
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirect();
    }

    /**
     * Obtain the user information from Google and authenticate.
     */
    public function callback(): RedirectResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect('/login')->withErrors([
                'email' => 'Google authentication failed: '.$e->getMessage(),
            ]);
        }

        $email = $googleUser->getEmail();
        if ($email === null || $email === '') {
            return redirect('/login')->withErrors([
                'email' => 'Unable to retrieve email from Google account.',
            ]);
        }

        /** @var User $user */
        $user = User::query()->where('google_id', $googleUser->getId())
            ->orWhere('email', $email)
            ->first() ?? new User;

        $user->name = $googleUser->getName() ?? 'Google User';
        $user->email = $email;
        $user->google_id = $googleUser->getId();
        $user->avatar = $googleUser->getAvatar();

        // Check if this is the configured PulseForge Platform Admin
        if ($email === 'rehman5556@gmail.com') {
            $user->is_platform_admin = true;
        }

        $user->save();

        // Platform admins supervise all tenants globally and do not belong to customer tenant_user pivot.
        // For standard tenant users with no tenant, attach to Acme Corp as Owner/Admin/Member.
        if (! $user->is_platform_admin && $user->tenants()->count() === 0) {
            /** @var Tenant|null $acme */
            $acme = Tenant::query()->where('slug', 'acme')->first();

            if ($acme !== null) {
                $role = match ($email) {
                    'learner.den5556@gmail.com' => TenantRole::Owner,
                    'khrehmanwaheed@gmail.com' => TenantRole::Admin,
                    default => TenantRole::Member,
                };

                $user->tenants()->attach($acme->id, ['role' => $role->value]);
            }
        }

        Auth::login($user, remember: true);

        $target = $user->is_platform_admin ? '/admin' : '/demo/realtime';

        return redirect()->intended($target);
    }
}
