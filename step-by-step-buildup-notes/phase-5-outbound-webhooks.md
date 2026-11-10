# Phase 5: Outbound Webhooks & Race Condition Defense #3

## 1. Overview
Phase 5 implements an enterprise-grade outbound webhook delivery pipeline allowing tenants to subscribe external endpoints (Slack, PagerDuty, Discord, custom API sinks) to incident and health check events. It includes cryptographically signed payloads (HMAC SHA-256) and an atomic state transition pattern that defends against duplicate webhook delivery during concurrent queue retries.

---

## 2. Implemented Components & Files

### A. Cryptographic Security & Signatures
- [`app/Services/Webhooks/WebhookSigner.php`](../app/Services/Webhooks/WebhookSigner.php):
  - Automatically provisions cryptographically secure secret tokens prefixed with `whsec_`.
  - Calculates HMAC SHA-256 signatures over raw JSON payloads.
  - Implements constant-time signature validation (`hash_equals`) to mitigate timing attacks.

### B. Outbound Webhook Models & Schemas
- [`app/Models/WebhookEndpoint.php`](../app/Models/WebhookEndpoint.php):
  - Manages subscriber URLs, secret tokens, active flags, and event filters (`*`, `incident.opened`, `incident.resolved`, etc.).
- [`app/Models/WebhookDelivery.php`](../app/Models/WebhookDelivery.php):
  - Audit log for each delivery attempt storing `event`, `payload`, `status`, `attempts`, `response_status_code`, `response_body`, and `error_message`.
- Enums:
  - [`WebhookEvent`](../app/Enums/WebhookEvent.php): `IncidentOpened`, `IncidentResolved`, `MonitorDown`, `MonitorRecovered`.
  - [`WebhookDeliveryStatus`](../app/Enums/WebhookDeliveryStatus.php): `Pending`, `InFlight`, `Delivered`, `Failed`.

### C. Race Condition Defense #3: Atomic State Transitions
- [`app/Actions/Webhooks/DispatchWebhookDeliveryAction.php`](../app/Actions/Webhooks/DispatchWebhookDeliveryAction.php):
  - Solves the multi-worker stampede/retry duplication problem using direct atomic SQL conditional updates:
    ```sql
    UPDATE webhook_deliveries
    SET status = 'in_flight', attempts = attempts + 1, last_attempt_at = NOW()
    WHERE id = ? AND status = 'pending';
    ```
  - If `affected_rows === 0`: Another concurrent queue worker has already claimed or delivered the payload. The current worker immediately terminates, guaranteeing that external webhook endpoints are never spammed with duplicates.
  - If `affected_rows === 1`: The worker proceeds to sign the payload and transmit it via HTTP POST.

### D. Exponential Backoff & Queue Workers
- [`app/Jobs/SendWebhookDeliveryJob.php`](../app/Jobs/SendWebhookDeliveryJob.php):
  - Dedicated queue job on the `webhooks` Redis queue.
  - Exponential backoff matrix: `[10, 60, 300, 1800]` seconds.
  - If an endpoint returns 5xx or connection times out, the delivery reverts to `pending` and releases with the scheduled backoff delay.
  - When all retry attempts are exhausted, the delivery status marks as `failed`.
- [`app/Actions/Webhooks/QueueWebhookForEventAction.php`](../app/Actions/Webhooks/QueueWebhookForEventAction.php):
  - Scans active endpoints matching the event and queues initial deliveries.

---

## 3. Verification & Test Suite
- **Pest Suite ([`tests/Feature/WebhookDeduplicationTest.php`](../tests/Feature/WebhookDeduplicationTest.php)):**
  - Concurrency test proving atomic state transitions prevent duplicate HTTP dispatch even when multiple workers process the same delivery.
  - Header inspection verifying `X-PulseForge-Signature`, `X-PulseForge-Event`, and `X-PulseForge-Delivery`.
  - Subscriber 500 error and retry attempt increment.
  - Exhaustion test transitioning to `failed` after maximum retries.
  - Queue event filtering ensuring inactive/unsubscribed endpoints do not receive dispatches.
- **Unit Suite ([`tests/Unit/WebhookSignerTest.php`](../tests/Unit/WebhookSignerTest.php)):**
  - HMAC SHA-256 calculation and constant-time validation.
- **Pest PHP 3 Summary:** **41 tests passed (126 assertions)**.
- **Larastan (Level 8):** **`[OK] No errors`** across all 50 files.
- **Laravel Pint:** 100% formatted.
