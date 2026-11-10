# Phase 2: Multi-Tenancy Architecture (Foundation & Scoping)

## 1. Architectural Philosophy
PulseForge utilizes a **single-database multi-tenant model with automated tenant scoping**. This pattern is standard for modern high-throughput SaaS platforms (like Datadog and Better Stack), allowing:
- Unified migrations and schema management across MySQL 8.0.
- Cross-tenant aggregate analytics for system administrators via `withoutTenancy()` query macros.
- Connection pool efficiency without per-tenant database connection overhead.

---

## 2. Implemented Components & Files

### A. Domain Entities & Enums
- [`app/Enums/TenantPlan.php`](../app/Enums/TenantPlan.php):
  - Backed string enum: `Free`, `Pro`, `Enterprise`.
  - Encapsulates domain logic: `defaultMonitorLimit()`, `checkIntervalSeconds()`, `hasAiPostMortem()`, and `rateLimitPerMinute()`.
- [`app/Enums/TenantRole.php`](../app/Enums/TenantRole.php):
  - Backed string enum: `Owner`, `Admin`, `Member`.
  - Role-based capabilities: `canManageBilling()`, `canManageTeam()`, `canMutateMonitors()`.
- [`app/Models/Tenant.php`](../app/Models/Tenant.php):
  - Manages tenant identity, subscription status, monitor quotas, and team relationships.
- [`app/Models/User.php`](../app/Models/User.php):
  - Equipped with `HasApiTokens` (Sanctum) and `tenants()` many-to-many relationship with role pivots.
- [`app/Models/TenantSetting.php`](../app/Models/TenantSetting.php):
  - Dedicated tenant-scoped key-value configuration entity using `BelongsToTenant`.

### B. Tenancy Resolution & Context (`TenantContext`)
- [`app/Services/Tenancy/TenantContext.php`](../app/Services/Tenancy/TenantContext.php):
  - Bound as a Singleton in [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php).
  - Holds active workspace context during HTTP requests and queue execution.
  - Fail-closed: `getOrFail()` throws `RuntimeException` if accessed without active tenant context.

### C. Automatic Isolation via `BelongsToTenant` & `TenantScope`
- [`app/Scopes/TenantScope.php`](../app/Scopes/TenantScope.php):
  - Injects `WHERE {table}.tenant_id = ?` into all queries when `TenantContext` is active.
  - Implements the `withoutTenancy()` Eloquent query macro for administrative maintenance queries.
- [`app/Traits/BelongsToTenant.php`](../app/Traits/BelongsToTenant.php):
  - Applies `TenantScope` globally to models.
  - `creating` hook: Automatically injects `tenant_id` from the active context if not explicitly set. Throws `RuntimeException` if created without tenant context.
  - `updating` hook: Prevents reassigning `tenant_id` on existing records, blocking tenant-hijacking vulnerabilities.

### D. Middleware Pipeline (`IdentifyTenant`)
- [`app/Http/Middleware/IdentifyTenant.php`](../app/Http/Middleware/IdentifyTenant.php):
  - Dual-resolution strategy:
    1. HTTP Header: `X-Tenant-Slug` or `X-Tenant-ID` (preferred for REST API & testing).
    2. Subdomain: `{slug}.localhost:8080` (for web dashboards, ignoring reserved subdomains like `www`, `api`, `admin`).
    3. Custom Domain: Matches against `tenants.domain`.
  - Registered as `tenant` route middleware alias in [`bootstrap/app.php`](../bootstrap/app.php).
  - Returns `404 Not Found` for unknown tenants, and `403 Forbidden` for suspended/inactive tenants.

---

## 3. Database Schema Migrations
- `database/migrations/2026_11_10_140000_create_tenants_and_tenant_users_tables.php`:
  - `tenants`: `id`, `name`, `slug` (unique), `domain` (unique), `plan`, `monitor_limit`, `is_active`, `stripe_customer_id`, `stripe_subscription_id`, `timestamps`.
  - `tenant_user`: `id`, `tenant_id` (foreign key), `user_id` (foreign key), `role`, `timestamps`. Unique constraint on `[tenant_id, user_id]`.
- `database/migrations/2026_11_10_141000_create_tenant_settings_table.php`:
  - `tenant_settings`: `id`, `tenant_id` (foreign key), `key`, `value`, `timestamps`. Unique constraint on `[tenant_id, key]`.

---

## 4. Test Verification (Pest PHP 3 & Larastan)
- **Unit Tests:**
  - [`tests/Unit/TenantPlanTest.php`](../tests/Unit/TenantPlanTest.php): 4 tests verifying plan quotas, check intervals, AI post-mortem gates, and rate limits.
  - [`tests/Unit/TenantContextTest.php`](../tests/Unit/TenantContextTest.php): 3 tests verifying singleton storage, state resets, and exception throwing.
- **Feature Tests:**
  - [`tests/Feature/TenantScopingTest.php`](../tests/Feature/TenantScopingTest.php):
    - Subdomain resolution & `X-Tenant-Slug` header resolution.
    - 404 on unknown tenants & 403 on suspended workspaces.
    - Strict query isolation: Tenant A cannot view Tenant B's records.
    - Automated `tenant_id` stamping on creation.
    - Exception when attempting to assign a different `tenant_id`.
    - `withoutTenancy()` bypass macro verified for administrative access.
- **Total Test Results:** 21 passed (49 assertions).
- **Larastan (Level 8):** `[OK] No errors`.
- **Laravel Pint:** 100% formatted.
