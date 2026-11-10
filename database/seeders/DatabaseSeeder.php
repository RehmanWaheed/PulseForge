<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MonitorMethod;
use App\Enums\MonitorStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantRole;
use App\Models\Monitor;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookSigner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a live demo scenario.
     */
    public function run(): void
    {
        // 1. Create Team User
        $user = User::query()->firstOrCreate(
            ['email' => 'admin@pulseforge.test'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Create Tenant A: Acme Corp (Pro Plan)
        /** @var Tenant $acme */
        $acme = Tenant::query()->firstOrCreate(
            ['slug' => 'acme'],
            [
                'name' => 'Acme Corporation',
                'plan' => TenantPlan::Pro,
                'monitor_limit' => 50,
                'is_active' => true,
                'stripe_customer_id' => 'cus_demo_acme_123',
                'stripe_subscription_id' => 'sub_demo_acme_123',
            ]
        );

        // Attach user to Acme as Owner
        if (! $user->belongsToTenant($acme)) {
            $user->tenants()->attach($acme->id, ['role' => TenantRole::Owner->value]);
        }

        // 3. Create Tenant B: Cyberdyne (Free Plan - to show strict multi-tenancy isolation)
        Tenant::query()->firstOrCreate(
            ['slug' => 'cyberdyne'],
            [
                'name' => 'Cyberdyne Systems',
                'plan' => TenantPlan::Free,
                'monitor_limit' => 5,
                'is_active' => true,
            ]
        );

        // 4. Create Real-world Monitors for Acme Corp
        // Monitor 1: Google (Always healthy)
        Monitor::query()->firstOrCreate(
            ['tenant_id' => $acme->id, 'url' => 'https://www.google.com'],
            [
                'name' => 'Primary Search Gateway',
                'method' => MonitorMethod::Get,
                'check_interval_seconds' => 60,
                'timeout_seconds' => 5,
                'expected_status_code' => 200,
                'consecutive_failure_threshold' => 3,
                'consecutive_failures' => 0,
                'status' => MonitorStatus::Healthy,
                'is_active' => true,
                'ssl_check_enabled' => true,
                'last_checked_at' => null,
            ]
        );

        // Monitor 2: GitHub API (Always healthy)
        Monitor::query()->firstOrCreate(
            ['tenant_id' => $acme->id, 'url' => 'https://api.github.com'],
            [
                'name' => 'GitHub Public API',
                'method' => MonitorMethod::Get,
                'check_interval_seconds' => 60,
                'timeout_seconds' => 5,
                'expected_status_code' => 200,
                'consecutive_failure_threshold' => 3,
                'consecutive_failures' => 0,
                'status' => MonitorStatus::Healthy,
                'is_active' => true,
                'ssl_check_enabled' => true,
                'last_checked_at' => null,
            ]
        );

        // Monitor 3: Broken Service (Fails to demonstrate outage & incident detection)
        Monitor::query()->firstOrCreate(
            ['tenant_id' => $acme->id, 'url' => 'https://httpbin.org/status/503'],
            [
                'name' => 'Payment Processing Service (Failing Demo)',
                'method' => MonitorMethod::Get,
                'check_interval_seconds' => 15,
                'timeout_seconds' => 5,
                'expected_status_code' => 200,
                'consecutive_failure_threshold' => 2,
                'consecutive_failures' => 0,
                'status' => MonitorStatus::Healthy,
                'is_active' => true,
                'ssl_check_enabled' => true,
                'last_checked_at' => null,
            ]
        );

        // 5. Create a Webhook Endpoint for Acme Corp
        WebhookEndpoint::query()->firstOrCreate(
            ['tenant_id' => $acme->id, 'url' => 'https://webhook.site/demo-pulseforge-sink'],
            [
                'secret' => app(WebhookSigner::class)->generateSecret(),
                'events' => ['*'],
                'is_active' => true,
                'description' => 'Slack & PagerDuty Integration Channel',
            ]
        );
    }
}
