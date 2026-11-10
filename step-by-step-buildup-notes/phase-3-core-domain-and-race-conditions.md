# Phase 3: Core Domain & Concurrency Defenses

## 1. Domain Overview
In Phase 3, we implemented the core health-checking entities: **Monitors**, **Monitor Check Results**, and **Incidents**, accompanied by two enterprise-grade concurrency defenses designed to eliminate race conditions under high queue/traffic loads.

---

## 2. Race Condition Scenarios & Senior Implementations

### A. Subscription Quota Defense (Pessimistic DB Row Lock)
- **The Problem:** In a high-concurrency environment, an automated API client or script could fire 10 simultaneous `POST /api/v1/monitors` requests for a tenant whose quota only allows 1 more monitor. Standard non-locking validation (`$count < $limit`) would see `$count` as valid in all 10 parallel requests before any row is committed, creating 10 monitors and bypassing billing restrictions.
- **The Implementation ([`app/Actions/Monitors/CreateMonitorAction.php`](../app/Actions/Monitors/CreateMonitorAction.php)):**
  - Executes inside a database transaction.
  - Acquires a pessimistic lock (`lockForUpdate()`) on the `tenants` record.
  - Recalculates `$lockedTenant->monitors()->count()` directly under the lock.
  - Throws [`SubscriptionQuotaExceededException`](../app/Exceptions/SubscriptionQuotaExceededException.php) if the limit is reached.
  - Tested in [`tests/Feature/SubscriptionQuotaLockTest.php`](../tests/Feature/SubscriptionQuotaLockTest.php).

---

### B. Worker Stampede Defense (Redis Atomic Locks + DB Row Lock)
- **The Problem:** When an external target service goes down or times out, multiple queue workers or scheduled runners may evaluate consecutive checks nearly simultaneously. Without concurrency guards, all workers see "Monitor has failed threshold" and each inserts a new `Incident`, spamming users and webhooks with duplicate incident notifications.
- **The Implementation ([`app/Actions/Incidents/ProcessMonitorFailureAction.php`](../app/Actions/Incidents/ProcessMonitorFailureAction.php)):**
  1. **Distributed Layer (Redis Atomic Lock):**
     Acquires an atomic lock on the monitor key (`Cache::lock("incident:monitor:{$monitor->id}", 10)->block(5, ...)`).
  2. **Database Layer (Pessimistic Row Lock):**
     Inside the lock and transaction, queries existing open incidents with `FOR UPDATE`:
     ```php
     $existingIncident = Incident::query()
         ->where('monitor_id', $lockedMonitor->id)
         ->whereNull('resolved_at')
         ->lockForUpdate()
         ->first();
     ```
  3. If an incident is already open, it returns the existing incident and increments logs without creating duplicates.
  4. Tested in [`tests/Feature/WorkerStampedeLockTest.php`](../tests/Feature/WorkerStampedeLockTest.php) (3/3 passing).

---

## 3. Database Schema Migrations Completed
- `database/migrations/2026_11_10_142000_create_monitors_table.php`:
  - `monitors`: `id`, `tenant_id`, `name`, `url`, `method`, `check_interval_seconds`, `timeout_seconds`, `expected_status_code`, `consecutive_failure_threshold`, `consecutive_failures`, `status`, `is_active`, `ssl_check_enabled`, `ssl_expires_at`, `last_checked_at`, `last_status_change_at`, `timestamps`.
- `database/migrations/2026_11_10_142100_create_monitor_check_results_table.php`:
  - `monitor_check_results`: `id`, `tenant_id`, `monitor_id`, `status_code`, `response_time_ms`, `is_successful`, `error_message`, `ssl_is_valid`, `ssl_days_remaining`, `checked_at`, `timestamps`.
- `database/migrations/2026_11_10_142200_create_incidents_table.php`:
  - `incidents`: `id`, `tenant_id`, `monitor_id`, `title`, `status`, `severity`, `description`, `root_cause_analysis`, `started_at`, `resolved_at`, `timestamps`.

---

## 4. Architecture & Quality Verification
- **Architecture Tests ([`tests/Feature/ArchitectureTest.php`](../tests/Feature/ArchitectureTest.php)):**
  - Actions must be invokable (`toBeInvokable`).
  - DTOs must be readonly (`toBeReadonly`).
  - Models must extend Eloquent Model.
  - Strict types (`declare(strict_types=1);`) across `App\`.
  - Zero debug helpers (`dd`, `dump`, `ray`).
- **Larastan (Level 8):** `[OK] No errors` across 39 files.
- **Pest Suite:** 28 passed (82 assertions).
- **Pint:** 100% formatted.
