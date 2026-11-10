# Phase 1: Environment & Application Baseline

## 1. Overview
Phase 1 established a fully containerized, modern Laravel environment on Windows 11 without relying on the host's legacy PHP 7.4 runtime, while maintaining complete isolation from existing local Docker projects (`BMF-connect-L13`).

---

## 2. Infrastructure & Port Mapping Matrix

To guarantee zero port collision with active projects on the machine, PulseForge is wired to isolated network ports and dedicated Docker bridge networks:

| Service | Container Name | Host Port | Internal Port | Isolation Guarantee |
| :--- | :--- | :--- | :--- | :--- |
| **Nginx Web Server** | `pulseforge-web` | `8080` | `80` | Coexists with BMF on `8000` |
| **MySQL 8.0** | `pulseforge-mysql` | `33077` | `3306` | Coexists with BMF on `33067` / `33061` |
| **Redis (phpredis)** | `pulseforge-redis` | `6380` | `6379` | Coexists with BMF on `6379` |
| **Mailpit Web UI** | `pulseforge-mailpit` | `8025` | `8025` | Dedicated web dashboard |
| **Mailpit SMTP** | `pulseforge-mailpit` | `1025` | `1025` | Dedicated local mail sink |
| **Laravel Reverb (Planned)** | `pulseforge-reverb` | `8085` | `8080` | Prevents collision with Nginx on `8080` |

---

## 3. Container Specification (`Dockerfile`)
- **Base Image:** `php:8.4-fpm-alpine`
- **Compiled Extensions:**
  - `pdo_mysql` (Database)
  - `redis` via PECL (High-performance caching, Horizon queues, atomic locks)
  - `pcntl` (Queue worker process control and signal handling)
  - `bcmath` (Precision calculations for financial billing/Stripe)
  - `intl` (Internationalization)
  - `zip` & `gd` (Media and export processing)
- **Composer:** Embedded `composer:latest` binary.

---

## 4. Scaffolded Framework & Tooling Stack
- **Framework:** Laravel 13.40 (PHP 8.4).
- **Session & Cache Driver:** Redis (`phpredis`) with prefix `pulseforge_cache_`.
- **Queue Connection:** Redis.
- **Testing Framework:** Pest PHP 3 (with `pestphp/pest-plugin-laravel` and `pestphp/pest-plugin-arch`).
- **Static Analysis:** Larastan (PHPStan) at **Level 8** with strict parameter checking.
- **Code Formatter:** Laravel Pint (Laravel standard ruleset).
- **Agent Guidelines:** Laravel Boost installed with tailored Pest and PHP rules.

---

## 5. Verification Checklist Completed
- [x] Docker containers boot cleanly via `docker compose up -d`.
- [x] HTTP GET `http://localhost:8080` returns HTTP 200 with active session cookie.
- [x] MySQL 8.0 migrations run successfully (`users`, `cache`, `jobs`).
- [x] Redis cache write/read verified (`REDIS_OK`).
- [x] Pest architecture tests pass:
  - Strict types (`declare(strict_types=1);`) enforced across `App\`.
  - Zero debug calls (`dd`, `dump`, `ray`, `var_dump`).
  - Eloquent model inheritance verified.
- [x] Larastan Level 8 static analysis reports `[OK] No errors`.
- [x] Laravel Pint reports 28/28 files clean.

