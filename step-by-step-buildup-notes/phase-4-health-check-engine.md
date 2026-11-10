# Phase 4: Health Check & Monitoring Engine

## 1. Overview
Phase 4 implements the automated network inspection engine that probes HTTP/HTTPS endpoints, tracks latency with millisecond precision, handles network timeouts, and coordinates with our Phase 3 concurrency actions to update monitor health and incident lifecycles.

---

## 2. Implemented Components & Files

### A. Network Probe Action ([`app/Actions/Monitors/PerformMonitorCheckAction.php`](../app/Actions/Monitors/PerformMonitorCheckAction.php))
- **Role:** Single-responsibility invokable class that probes the target endpoint.
- **Latency Timer:** Uses `hrtime(true)` for sub-millisecond precision.
- **Header Fingerprint:** Sets custom User-Agent `PulseForge-HealthChecker/1.0 (+https://pulseforge.test)`.
- **Status Validation:** Compares actual HTTP status code against `$monitor->expected_status_code`.
- **Fault Tolerance:** Traps `Throwable` and connection exceptions (e.g. DNS timeouts, connection refused) and converts them into [`MonitorCheckResultDTO::failure()`](../app/DTOs/MonitorCheckResultDTO.php) without crashing the queue worker.

### B. Redis Asynchronous Queue Worker Job ([`app/Jobs/CheckMonitorJob.php`](../app/Jobs/CheckMonitorJob.php))
- **Role:** Distributed queue job dispatched onto the `monitoring` Redis queue.
- **Coordination:**
  - Invokes `PerformMonitorCheckAction`.
  - On success: Dispatches [`ProcessMonitorRecoveryAction`](../app/Actions/Incidents/ProcessMonitorRecoveryAction.php) to reset consecutive failures and resolve any open incidents.
  - On failure: Dispatches [`ProcessMonitorFailureAction`](../app/Actions/Incidents/ProcessMonitorFailureAction.php) using Redis atomic locking to prevent duplicate incident creation.

### C. Dispatcher Command ([`app/Console/Commands/DispatchMonitorChecksCommand.php`](../app/Console/Commands/DispatchMonitorChecksCommand.php))
- **Artisan Command:** `php artisan monitors:dispatch-checks`
- **Scheduler Integration ([`routes/console.php`](../routes/console.php)):** Runs every minute with `withoutOverlapping()`.
- **Dual-Database Query Scope ([`Monitor::dueForCheck()`](../app/Models/Monitor.php#L147)):**
  - Cross-platform time-interval evaluation:
    - **MySQL 8.0:** `TIMESTAMPDIFF(SECOND, last_checked_at, NOW()) >= check_interval_seconds`
    - **SQLite (:memory: testing):** `datetime(last_checked_at, '+' || check_interval_seconds || ' seconds') <= datetime('now')`
  - Bypasses multi-tenant isolation via `withoutGlobalScope(TenantScope::class)` so workers inspect active monitors across all tenant workspaces.

---

## 3. Verification & Test Suite
- **Pest Suite ([`tests/Feature/MonitorCheckEngineTest.php`](../tests/Feature/MonitorCheckEngineTest.php)):**
  - Probing valid 200 OK endpoints via `Http::fake()`.
  - Mismatched HTTP status codes (e.g. expecting 200, receiving 503).
  - Network connection timeouts via `ConnectionException`.
  - End-to-end `CheckMonitorJob` execution transitioning monitors to `Down` and spawning an `Incident`.
  - Scheduler test verifying `monitors:dispatch-checks` only dispatches due monitors and ignores fresh or inactive monitors.
- **Pest PHP 3 Summary:** **33 tests passed (105 assertions)** across all unit and feature suites.
- **Larastan (Level 8):** **`[OK] No errors`** across all 42 files.
- **Laravel Pint:** 100% formatted.
