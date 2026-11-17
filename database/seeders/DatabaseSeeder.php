<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\MonitorMethod;
use App\Enums\MonitorStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantRole;
use App\Models\Incident;
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
        // 1. Create Users
        // 1a. Acme Corporation Owner
        $owner = User::query()->firstOrCreate(
            ['email' => 'learner.den5556@gmail.com'],
            [
                'name' => 'Acme Owner',
                'password' => Hash::make('password'),
                'is_platform_admin' => false,
            ]
        );
        if ($owner->password === null) {
            $owner->password = Hash::make('password');
            $owner->save();
        }

        // 1b. Acme Corporation Admin
        $admin = User::query()->firstOrCreate(
            ['email' => 'khrehmanwaheed@gmail.com'],
            [
                'name' => 'Acme Admin',
                'password' => Hash::make('password'),
                'is_platform_admin' => false,
            ]
        );
        if ($admin->password === null) {
            $admin->password = Hash::make('password');
            $admin->save();
        }

        // 1c. PulseForge Platform Super-Admin (Global SaaS Operator - Not scoped to customer tenants)
        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'rehman5556@gmail.com'],
            [
                'name' => 'PulseForge Platform Admin',
                'password' => Hash::make('password'),
                'is_platform_admin' => true,
            ]
        );
        if ($superAdmin->password === null) {
            $superAdmin->password = Hash::make('password');
            $superAdmin->save();
        }

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

        // Attach tenant users to Acme (Only customer members belong to tenant_user)
        if (! $owner->belongsToTenant($acme)) {
            $owner->tenants()->attach($acme->id, ['role' => TenantRole::Owner->value]);
        }

        if (! $admin->belongsToTenant($acme)) {
            $admin->tenants()->attach($acme->id, ['role' => TenantRole::Admin->value]);
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

        // 6. Create an Active Incident for War Room Demo
        $failingMonitor = Monitor::query()->where('url', 'https://httpbin.org/status/503')->first();
        if ($failingMonitor !== null) {
            Incident::query()->firstOrCreate(
                ['tenant_id' => $acme->id, 'title' => 'Payment Gateway Latency Spike & 503 Errors'],
                [
                    'monitor_id' => $failingMonitor->id,
                    'status' => IncidentStatus::Investigating,
                    'severity' => IncidentSeverity::Major,
                    'description' => 'Payment Processing Service returned consecutive 503 Service Unavailable errors. Automated incident response initiated.',
                    'started_at' => now()->subMinutes(12),
                ]
            );
        }
    }
}
