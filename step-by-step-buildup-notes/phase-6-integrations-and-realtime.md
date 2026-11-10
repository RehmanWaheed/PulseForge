# Phase 6: Third-Party Sandboxes, AI Post-Mortems & Real-Time Alerting

## 1. Overview
Phase 6 connects PulseForge with external cloud integrations, AI services, and real-time infrastructure:
1. **Google Gemini API (Free Tier):** AI-powered Root Cause Analysis (RCA) and Incident Post-Mortem generation upon incident resolution.
2. **Stripe Test Mode:** Automated subscription lifecycle management via webhooks (`customer.subscription.created/updated/deleted`), dynamically provisioning monitor quotas.
3. **Laravel Reverb (WebSockets):** Real-time event broadcasting on private tenant channels (`tenant.{id}`) for live dashboard heartbeats and urgent outage alerts.

---

## 2. Implemented Components & Files

### A. AI Incident Post-Mortem Generator (Google Gemini API)
- **Service Client ([`app/Services/AI/GeminiClient.php`](../app/Services/AI/GeminiClient.php)):**
  - Interacts with Google Gemini's REST API endpoint (`v1beta/models/gemini-1.5-flash:generateContent`).
  - Configured in [`config/services.php`](../config/services.php) using environment variable `GEMINI_API_KEY`.
  - Traps HTTP/rate-limit exceptions and connection timeouts gracefully without disrupting queue workers.
- **Action Class ([`app/Actions/Incidents/GenerateIncidentPostMortemAction.php`](../app/Actions/Incidents/GenerateIncidentPostMortemAction.php)):**
  - Triggered automatically when an incident is resolved.
  - **Plan Gating:** Workspaces on the `Free` tier are prevented from generating AI post-mortems; `Pro` & `Enterprise` workspaces receive auto-generated RCAs.
  - Feeds technical telemetry (HTTP error codes, timeout durations, outage length) to Gemini and stores the structured RCA markdown directly on the `incidents.root_cause_analysis` column.

### B. Stripe Test Mode Subscription Lifecycle
- **Action Class ([`app/Actions/Billing/HandleStripeWebhookAction.php`](../app/Actions/Billing/HandleStripeWebhookAction.php)):**
  - Processes incoming Stripe webhook events (`customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`).
  - Upgrades or downgrades tenant plan tier (`TenantPlan::Free`, `TenantPlan::Pro`, `TenantPlan::Enterprise`).
  - Automatically synchronizes `monitor_limit` quotas under pessimistic locks.
- **Webhook Route ([`routes/api.php`](../routes/api.php)):**
  - Exposed publicly at `POST /api/webhooks/stripe`.

### C. Real-Time Broadcast Events (Laravel Reverb)
- **[`app/Events/MonitorCheckedEvent.php`](../app/Events/MonitorCheckedEvent.php):** Broadcasts sub-second latency and heartbeat pulses to subscriber dashboards on `PrivateChannel("tenant.{tenantId}")`.
- **[`app/Events/IncidentCreatedEvent.php`](../app/Events/IncidentCreatedEvent.php):** Emits high-priority outage notifications to active team members.
- **[`app/Events/IncidentResolvedEvent.php`](../app/Events/IncidentResolvedEvent.php):** Emits recovery confirmation with outage duration metrics.

---

## 3. End-to-End Pipeline Integration
1. **Check Execution:** When [`CheckMonitorJob`](../app/Jobs/CheckMonitorJob.php) executes, it emits `MonitorCheckedEvent`.
2. **Outage Detection:** When [`ProcessMonitorFailureAction`](../app/Actions/Incidents/ProcessMonitorFailureAction.php) detects threshold breach:
   - Opens the single `Incident` under Redis atomic locking.
   - Dispatches `IncidentCreatedEvent` on `tenant.{tenantId}`.
   - Queues `WebhookEvent::IncidentOpened` to all active subscribed endpoints.
3. **Outage Recovery:** When [`ProcessMonitorRecoveryAction`](../app/Actions/Incidents/ProcessMonitorRecoveryAction.php) observes recovery:
   - Resolves the `Incident` atomically.
   - Dispatches `IncidentResolvedEvent` on `tenant.{tenantId}`.
   - Generates AI Post-Mortem via Gemini if on `Pro` or `Enterprise`.
   - Queues `WebhookEvent::IncidentResolved` to all active subscribed endpoints.

---

## 4. Verification & Testing Suite
- **Pest Feature Suites:**
  - [`tests/Feature/GeminiPostMortemTest.php`](../tests/Feature/GeminiPostMortemTest.php): Gating verification, AI response mocking via `Http::fake()`, and exception resilience.
  - [`tests/Feature/StripeSubscriptionLifecycleTest.php`](../tests/Feature/StripeSubscriptionLifecycleTest.php): Automated subscription provisioning, tier upgrades, and cancellation downgrades.
  - [`tests/Feature/RealTimeAlertsTest.php`](../tests/Feature/RealTimeAlertsTest.php): Broadcast assertions on private tenant channels using `Event::fake()`.
- **Total Pest PHP 3 Tests:** **49 passed (148 assertions)**.
- **Larastan (Level 8):** **`[OK] No errors`** across all 56 files.
- **Laravel Pint:** 100% formatted.
