# Phase 8: Authentication, Google OAuth 2.0, Duplicate Monitor Defense, and Platform Super-Admin Control Center

## 1. Overview
Phase 8 elevates **PulseForge** from an API & realtime prototype into a full-fledged, multi-tier SaaS platform with production-grade security, identity management, and operational supervision:
- **Duplicate Monitor Defense (Day One Schema Constraint):** Strict multi-tenant unique composite keys preventing duplicate monitoring targets and names within the same tenant.
- **Hybrid Authentication System:** Session-based authentication with CSRF protection supporting standard Email/Password and **Google OAuth 2.0 (Laravel Socialite)** with automatic attribute synchronization.
- **Clean Architectural Scoping:** Complete decoupling of the SaaS Platform Super-Admin from customer tenant workspaces (`tenant_user` pivot table).
- **Dedicated Platform Super-Admin Control Center (`/admin`):** Global executive overview of all tenant workspaces, probe health, subscription tiers, and live workspace inspection/auditing.
- **Dynamic Post-Login Routing:** Role-based redirection directing platform administrators to the Platform Hub and customer users to their scoped tenant workspace.
- **Synchronized Postman Collection:** Upgraded with dedicated authentication, duplicate collision tests, and platform administration workflows.

---

## 2. Key Architecture & Feature Implementations

### A. Duplicate Monitor Defense (Schema & Domain Protection)
To ensure database integrity and prevent worker race conditions from monitoring duplicate endpoints:
1. **Composite Unique Indexes ([`database/migrations/2026_11_10_142000_create_monitors_table.php`](../database/migrations/2026_11_10_142000_create_monitors_table.php)):**
   - `$table->unique(['tenant_id', 'url'], 'monitors_tenant_url_unique')`
   - `$table->unique(['tenant_id', 'name'], 'monitors_tenant_name_unique')`
2. **Domain Exception & Action Guard ([`CreateMonitorAction`](../app/Actions/Monitors/CreateMonitorAction.php)):**
   - Created `DuplicateMonitorException`.
   - Action checks for existing URL/Name within the tenant scope and throws domain-specific exception before attempting insertion.
   - Handled in API routes returning a user-friendly `HTTP 422 Unprocessable Entity` JSON response.
3. **Automated Tests ([`DuplicateMonitorDefenseTest`](../tests/Feature/DuplicateMonitorDefenseTest.php)):**
   - Rejects duplicate URL within the same tenant (422).
   - Rejects duplicate Name within the same tenant (422).
   - Confirms different tenants can monitor identical URLs (e.g. `https://google.com`) with zero collisions.

---

### B. Authentication & Google OAuth 2.0 System
1. **User Schema Extensions ([`0001_01_01_000000_create_users_table.php`](../database/migrations/0001_01_01_000000_create_users_table.php)):**
   - Added `google_id` (nullable, unique), `avatar` (nullable), `is_platform_admin` (boolean, indexed), and nullable `password`.
2. **Google Socialite Integration ([`GoogleAuthController`](../app/Http/Controllers/Auth/GoogleAuthController.php)):**
   - Installed `laravel/socialite` v5.32.0.
   - Configured `config/services.php` with `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI`.
   - Secure handshake via `Route::get('/auth/google')` and `Route::get('/auth/google/callback')`.
3. **Cyber-Dark Authentication UI ([`resources/views/auth/login.blade.php`](../resources/views/auth/login.blade.php)):**
   - Tailwind CSS styling matching PulseForge cyber-dark aesthetic.
   - Quick one-click credential autofill buttons for seeded accounts.
   - CSRF protection with automatic token handling.

---

### C. Decoupling Platform Super-Admin from Tenant Pivot (`tenant_user`)
In B2B multi-tenancy, platform operators must not exist inside customer tenant rosters:
- **The Issue:** Previously, the seeder attached `rehman5556@gmail.com` into `tenant_user` with `tenant_id = 1` (Acme Corp), polluting customer rosters and email alert dispatches.
- **The Solution ([`DatabaseSeeder.php`](../database/seeders/DatabaseSeeder.php)):**
  - Removed `$superAdmin->tenants()->attach($acme->id)`.
  - Only genuine customer accounts (`learner.den5556@gmail.com` as Owner, `khrehmanwaheed@gmail.com` as Admin) exist in `tenant_user`.
  - Guarded [`GoogleAuthController.php`](../app/Http/Controllers/Auth/GoogleAuthController.php) so Google login never re-attaches platform admins to customer pivots at runtime.
  - Updated [`routes/channels.php`](../routes/channels.php) so `$user->is_platform_admin` grants global observation rights across all tenant WebSocket channels without requiring a pivot record.

---

### D. Platform Super-Admin Control Center (`/admin`)
1. **Access Middleware ([`EnsurePlatformAdmin`](../app/Http/Middleware/EnsurePlatformAdmin.php)):**
   - Registered as `platform_admin` middleware alias in `bootstrap/app.php`.
   - Rejects non-platform admins with `HTTP 403 Forbidden`.
2. **Platform Admin Controller ([`PlatformAdminController`](../app/Http/Controllers/Admin/PlatformAdminController.php)):**
   - Gathers global SaaS metrics: Total Tenants (active vs suspended), Total Probes (healthy vs down), Open Incidents across all tenants, and System Engine health.
   - Loads the customer tenant directory with monitor quota utilization and team size.
   - Streams the latest 8 probe checks across all workspaces.
3. **Control Center View ([`resources/views/admin/dashboard.blade.php`](../resources/views/admin/dashboard.blade.php)):**
   - Cyber-dark layout with KPI metric cards, customer workspace directory, and real-time global telemetry logs.
   - Features **"Inspect Live Workspace 👁️"** action button for each tenant.
4. **Role-Based Post-Login Routing:**
   - **Platform Super-Admin:** Lands on `/admin`.
   - **Customer Tenant User:** Lands on `/demo/realtime`.
   - Root `/` automatically redirects based on user role.
5. **Workspace Audit Banner ([`resources/views/demo/realtime.blade.php`](../resources/views/demo/realtime.blade.php)):**
   - Top banner displayed when a super-admin inspects a customer workspace:
     `🛡️ Platform Admin Mode: Auditing Workspace: Acme Corporation [Switch Workspace] [← Return to Platform Hub]`

---

### E. Postman Collection Upgrade (`PulseForge.postman_collection.json`)
The collection was expanded to 7 folders and 32 total requests:
- **`0. Authentication (Session & Google OAuth)`:**
  - `View Login Page` (with auto-extraction of `csrf_token` into `{{csrf_token}}`).
  - `Login as Acme Owner` (`learner.den5556@gmail.com`).
  - `Login as Acme Admin` (`khrehmanwaheed@gmail.com`).
  - `Login as Platform Super Admin` (`rehman5556@gmail.com`).
  - `Login with Invalid Credentials (Expect 422)`.
  - `Google OAuth Redirect Entrypoint` (Expects 302 to Google).
  - `View Platform Admin Control Center (/admin)`.
  - `Logout`.
- **`3. Monitors (v1 API)`:**
  - Added `Duplicate Monitor Check - URL Collision (Expect 422)`.
  - Added `Duplicate Monitor Check - Name Collision (Expect 422)`.
- **`6. Laravel Reverb Realtime & War Room Chat`:**
  - Updated dashboard requests to use authenticated sessions.
  - Added `X-CSRF-TOKEN` headers to WebSocket triggers and War Room chat requests.
  - Added `Trigger Monitor Probe Check (On-Demand Telemetry)` with telemetry response assertions.

---

## 3. Seeded Accounts & Roles Summary

| Role | Email | Password | Scope | Default Landing Page |
|---|---|---|---|---|
| **Platform Super-Admin** | `rehman5556@gmail.com` | `password` | Global SaaS (`is_platform_admin = true`, 0 rows in `tenant_user`) | `/admin` |
| **Acme Corp Owner** | `learner.den5556@gmail.com` | `password` | Tenant 1 (`Acme Corporation`, `role = owner`) | `/demo/realtime` |
| **Acme Corp Admin** | `khrehmanwaheed@gmail.com` | `password` | Tenant 1 (`Acme Corporation`, `role = admin`) | `/demo/realtime` |

---

## 4. Verification & Quality Metrics
- **Pest PHP 3 Tests:** **80 passed (244 assertions)**.
  - `tests/Feature/AuthenticationTest.php`: 10 passed.
  - `tests/Feature/DuplicateMonitorDefenseTest.php`: 5 passed.
  - `tests/Feature/PlatformAdminDashboardTest.php`: 6 passed.
  - Complete regression coverage across tenancy, locks, workers, and billing.
- **Larastan (PHPStan Level 8):** **`[OK] No errors`** across all 71 application files.
- **Laravel Pint:** 100% formatted to Laravel standards (114 files clean).

