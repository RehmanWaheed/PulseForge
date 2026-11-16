<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

test('unauthenticated users cannot access platform admin dashboard and are redirected to login', function () {
    $response = $this->get('/admin');

    $response->assertRedirect('/login');
});

test('standard customer users receive 403 forbidden when attempting to access platform admin dashboard', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme']);
    $customerUser = User::factory()->create([
        'email' => 'customer@acme.com',
        'is_platform_admin' => false,
    ]);
    $customerUser->tenants()->attach($tenant->id, ['role' => 'owner']);

    $response = $this->actingAs($customerUser)->get('/admin');

    $response->assertStatus(403);
});

test('platform super admin can access platform dashboard and view all tenant workspaces', function () {
    $acme = Tenant::factory()->create(['name' => 'Acme Corporation', 'slug' => 'acme']);
    $cyberdyne = Tenant::factory()->create(['name' => 'Cyberdyne Systems', 'slug' => 'cyberdyne']);

    $superAdmin = User::factory()->create([
        'email' => 'rehman5556@gmail.com',
        'name' => 'Platform Operator',
        'is_platform_admin' => true,
    ]);

    $response = $this->actingAs($superAdmin)->get('/admin');

    $response->assertStatus(200)
        ->assertSee('Platform Super-Admin')
        ->assertSee('Customer Workspaces Directory')
        ->assertSee('Acme Corporation')
        ->assertSee('Cyberdyne Systems')
        ->assertSee('Inspect Live Workspace');
});

test('logging in as platform super admin redirects to /admin', function () {
    User::factory()->create([
        'email' => 'rehman5556@gmail.com',
        'password' => bcrypt('password'),
        'is_platform_admin' => true,
    ]);

    $response = $this->post('/login', [
        'email' => 'rehman5556@gmail.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin');
});

test('logging in as standard customer user redirects to /demo/realtime', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create([
        'email' => 'learner.den5556@gmail.com',
        'password' => bcrypt('password'),
        'is_platform_admin' => false,
    ]);
    $user->tenants()->attach($tenant->id, ['role' => 'owner']);

    $response = $this->post('/login', [
        'email' => 'learner.den5556@gmail.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/demo/realtime');
});

test('root url / redirects platform super admin to /admin and standard user to /demo/realtime', function () {
    $superAdmin = User::factory()->create(['is_platform_admin' => true]);
    $standardUser = User::factory()->create(['is_platform_admin' => false]);

    $this->actingAs($superAdmin)->get('/')->assertRedirect('/admin');
    $this->actingAs($standardUser)->get('/')->assertRedirect('/demo/realtime');
});
