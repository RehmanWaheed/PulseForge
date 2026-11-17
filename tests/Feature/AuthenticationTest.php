<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery\MockInterface;

test('unauthenticated users are redirected from protected realtime view to login', function () {
    $response = $this->get('/demo/realtime');

    $response->assertRedirect('/login');
});

test('login page renders successfully with branding and form fields', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('Sign In to Your Workspace')
        ->assertSee('Sign in with Google')
        ->assertSee('learner.den5556@gmail.com');
});

test('authenticated user can access dashboard and view their tenant workspace', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Corporation', 'slug' => 'acme']);
    $user = User::factory()->create([
        'email' => 'learner.den5556@gmail.com',
        'password' => bcrypt('password'),
    ]);
    $user->tenants()->attach($tenant->id, ['role' => 'owner']);

    $response = $this->actingAs($user)->get('/demo/realtime');

    $response->assertStatus(200)
        ->assertSee('Acme Corporation')
        ->assertSee('learner.den5556@gmail.com');
});

test('user can authenticate via email and password', function () {
    $user = User::factory()->create([
        'email' => 'khrehmanwaheed@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->post('/login', [
        'email' => 'khrehmanwaheed@gmail.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/demo/realtime');
    $this->assertAuthenticatedAs($user);
});

test('authentication fails with invalid credentials', function () {
    User::factory()->create([
        'email' => 'khrehmanwaheed@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->post('/login', [
        'email' => 'khrehmanwaheed@gmail.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('authenticated user can log out safely', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('google oauth redirect generates valid redirect response', function () {
    $response = $this->get('/auth/google');

    $response->assertRedirect();
});

test('google oauth callback authenticates existing user and syncs google attributes', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'learner.den5556@gmail.com',
    ]);
    $user->tenants()->attach($tenant->id, ['role' => 'owner']);

    $abstractUser = Mockery::mock(Laravel\Socialite\Two\User::class);
    $abstractUser->shouldReceive('getId')->andReturn('google-id-987654');
    $abstractUser->shouldReceive('getName')->andReturn('Acme Owner Google');
    $abstractUser->shouldReceive('getEmail')->andReturn('learner.den5556@gmail.com');
    $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/photo.jpg');

    /** @var MockInterface&GoogleProvider $provider */
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/demo/realtime');
    $this->assertAuthenticated();

    $freshUser = $user->fresh();
    expect($freshUser->google_id)->toBe('google-id-987654')
        ->and($freshUser->avatar)->toBe('https://lh3.googleusercontent.com/photo.jpg');
});

test('platform super admin can access any workspace without being in tenant_user table', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Corporation', 'slug' => 'acme']);
    $superAdmin = User::factory()->create([
        'email' => 'rehman5556@gmail.com',
        'is_platform_admin' => true,
    ]);

    expect($superAdmin->belongsToTenant($tenant))->toBeFalse()
        ->and(DB::table('tenant_user')->where('user_id', $superAdmin->id)->count())->toBe(0);

    $response = $this->actingAs($superAdmin)->get('/demo/realtime?tenant=acme');
    $response->assertStatus(200)
        ->assertSee('Acme Corporation')
        ->assertSee('Super Admin');
});

test('google oauth callback marks platform admin and does not attach to tenant_user table', function () {
    Tenant::factory()->create(['slug' => 'acme']);

    $abstractUser = Mockery::mock(Laravel\Socialite\Two\User::class);
    $abstractUser->shouldReceive('getId')->andReturn('google-id-superadmin');
    $abstractUser->shouldReceive('getName')->andReturn('Platform Super Admin');
    $abstractUser->shouldReceive('getEmail')->andReturn('rehman5556@gmail.com');
    $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/superadmin.jpg');

    /** @var MockInterface&GoogleProvider $provider */
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/admin');
    $this->assertAuthenticated();

    /** @var User $user */
    $user = User::query()->where('email', 'rehman5556@gmail.com')->firstOrFail();
    expect($user->is_platform_admin)->toBeTrue()
        ->and(DB::table('tenant_user')->where('user_id', $user->id)->count())->toBe(0);
});

test('successful email password login writes structured log with user and auth method', function () {
    Log::spy();

    $user = User::factory()->create([
        'email' => 'login-log-test@pulseforge.test',
        'password' => bcrypt('password'),
    ]);

    $this->post('/login', [
        'email' => 'login-log-test@pulseforge.test',
        'password' => 'password',
    ]);

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($user): bool {
        return $message === 'User authenticated successfully.'
            && $context['user_id'] === $user->id
            && $context['auth_method'] === 'email_password';
    });
});

test('failed login attempt writes warning log with attempted email and auth method', function () {
    Log::spy();

    $this->post('/login', [
        'email' => 'nonexistent@pulseforge.test',
        'password' => 'wrong-pass',
    ]);

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
        return $message === 'Failed login attempt.'
            && $context['email'] === 'nonexistent@pulseforge.test'
            && $context['auth_method'] === 'email_password';
    });
});

test('google oauth login writes structured log with google auth method', function () {
    Log::spy();
    Tenant::factory()->create(['slug' => 'acme']);

    $abstractUser = Mockery::mock(Laravel\Socialite\Two\User::class);
    $abstractUser->shouldReceive('getId')->andReturn('google-id-log-test');
    $abstractUser->shouldReceive('getName')->andReturn('OAuth Log User');
    $abstractUser->shouldReceive('getEmail')->andReturn('oauth-log-user@pulseforge.test');
    $abstractUser->shouldReceive('getAvatar')->andReturn(null);

    /** @var MockInterface&GoogleProvider $provider */
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback');

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'User authenticated successfully via Google OAuth.'
            && $context['email'] === 'oauth-log-user@pulseforge.test'
            && $context['auth_method'] === 'google_oauth';
    });
});
