# GameSense AI — Real-Time Tactical Analysis & Gaming Command Panel

A high-contrast **Neo-Brutalist / Hardcore Gaming UI** web application built with **PHP 8.3**, **Laravel 11**, **MySQL 8.0**, and **Pure Structured CSS3 Grid**, engineered to run within a **1 GB RAM / 1/8 OCPU Oracle Cloud Always Free (`VM.Standard.E2.1.Micro`)** instance.

- **Live Production URL:** [https://gsense.humbertorandon.com](https://gsense.humbertorandon.com)

---

## 1. Technical Architecture & Highlights

- **External AI API Integration (`gemini-3.5-flash-lite`) via `Http::post()`:**
  - Implemented in [`app/Services/AiTacticalService.php`](app/Services/AiTacticalService.php) using Laravel's HTTP Client with automatic retry and structured JSON output (`responseMimeType: application/json`), backed by a deterministic tactical engine for zero-downtime resilience.
- **Millisecond Caching via Normalized `SHA-256` Hashing:**
  - [`app/Services/TacticalPromptHasher.php`](app/Services/TacticalPromptHasher.php) normalizes incoming game telemetry and computes a deterministic `prompt_hash CHAR(64)` indexed as `UNIQUE` in MySQL and cached via Laravel's database cache store.
  - Identical or equivalent tactical queries return in **< 10 ms**, incrementing `hit_count` and saving external API calls.
- **Asynchronous Queue Processing (Laravel Database Queue):**
  - On a *Cache Miss*, [`app/Http/Controllers/Api/TacticAnalysisController.php`](app/Http/Controllers/Api/TacticAnalysisController.php) dispatches [`app/Jobs/GenerateTacticalAdviceJob.php`](app/Jobs/GenerateTacticalAdviceJob.php) to the background queue (`202 Accepted`) while the frontend polls `/api/tactics/status/{uuid}`.
- **Relational MySQL Schema (`games`, `categories`, `prompts_history`, `tactics_logs`):**
  - Strictly typed PHP 8.3 Eloquent ORM models with foreign key constraints and composite indexes.
- **Pure CSS3 Grid (Neo-Brutalist / Hardcore Gaming UI):**
  - [`public/css/neo-brutalism.css`](public/css/neo-brutalism.css) is written in 100% pure CSS3 without external CSS frameworks:
    - `border: 3px solid #000000; border-radius: 0px;`
    - Hard drop shadows (`box-shadow: 4px 4px 0px #000000;`) and physical `:active` button depression (`transform: translate(2px, 2px); box-shadow: 2px 2px 0px #000000;`)
    - Technical neutral canvas (`#F4F4F0` / `#EBEBE6`), pure black/white structural elements, and acid highlights (`#FFDE59` Terminal Yellow, `#FF5722` Alert Orange, `#00FF66` Monitor Green).

---

## 2. Oracle Cloud E2.1.Micro (1 GB RAM) Tuning Kit

Located in [`deploy/oracle-e2-micro/`](deploy/oracle-e2-micro/):
- [`mysql-low-memory.cnf`](deploy/oracle-e2-micro/mysql-low-memory.cnf): Reduces MySQL 8.0 footprint to ~128 MB RAM (`performance_schema = OFF`, `innodb_buffer_pool_size = 64M`).
- [`php-fpm-e2micro.conf`](deploy/oracle-e2-micro/php-fpm-e2micro.conf): Configures PHP 8.3-FPM with `pm = ondemand` (max 4 children) + OPcache & JIT.
- [`gamesense-worker.service`](deploy/oracle-e2-micro/gamesense-worker.service): Native `systemd` unit for the Laravel Queue Worker (`MemoryMax=96M`).
- [`nginx-subdomain.conf`](deploy/oracle-e2-micro/nginx-subdomain.conf): Nginx virtual host for `gsense.humbertorandon.com` with Let's Encrypt SSL.
