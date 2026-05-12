# Laravel Permissions Benchmark

A side-by-side benchmark comparing **DB query counts and wall-clock time** between [spatie/laravel-permission](https://github.com/spatie/laravel-permission) and [scabarcas/laravel-permissions-redis](https://github.com/scabarcas17/laravel-permissions-redis).

Tested against:
- `scabarcas/laravel-permissions-redis` **v4.0.0-beta.2** (resolved from `dev-main` via the VCS repository in `composer.json`)
- `spatie/laravel-permission` **^7.2**

## Methodology

For each iteration count we run **5 warm-up runs** (discarded) plus **30 measurement runs**, calling `gc_collect_cycles()` before every measurement so memory state doesn't drift run-to-run. Spatie's permission cache is flushed once before warm-up so both strategies start from the same baseline; from there each measurement run sees steady-state behaviour (cache hot for both packages).

We report **p50 (median), p95, p99, mean, and stddev** of wall-clock time. Numbers come from `php artisan bench:markdown --warm=5 --runs=30` on Apple Silicon, PHP 8.4, predis client, SQLite + local Redis. Reproducible on the same machine; not directly comparable across hosts.

Run yourself:

```bash
php artisan migrate:fresh --seed --seeder=BenchmarkSeeder
php artisan bench:markdown --warm=5 --runs=30
```

## Results

### 1 Iteration (27 permission checks + 4 role checks + 4 batch ops + 2 collection calls)

| Metric | spatie/laravel-permission | scabarcas/laravel-permissions-redis | Delta |
|--------|:---:|:---:|:---:|
| **DB Queries** | 4 | 1 | **75% fewer** |
| **Median (p50)** | 14.27 ms | 1.44 ms | **9.92x faster** |
| **p95** | 14.53 ms | 1.48 ms | — |
| **p99** | 14.56 ms | 1.52 ms | — |
| **Mean ± StdDev** | 14.30 ± 0.12 ms | 1.44 ± 0.02 ms | — |

### 10 Iterations

| Metric | spatie/laravel-permission | scabarcas/laravel-permissions-redis | Delta |
|--------|:---:|:---:|:---:|
| **DB Queries** | 40 | 10 | **75% fewer** |
| **Median (p50)** | 144.38 ms | 14.39 ms | **10.03x faster** |
| **p95** | 146.20 ms | 14.57 ms | — |
| **p99** | 147.13 ms | 14.58 ms | — |
| **Mean ± StdDev** | 144.25 ± 1.09 ms | 14.39 ± 0.10 ms | — |

### 50 Iterations

| Metric | spatie/laravel-permission | scabarcas/laravel-permissions-redis | Delta |
|--------|:---:|:---:|:---:|
| **DB Queries** | 200 | 50 | **75% fewer** |
| **Median (p50)** | 730.88 ms | 72.87 ms | **10.03x faster** |
| **p95** | 742.99 ms | 74.81 ms | — |
| **p99** | 743.94 ms | 75.08 ms | — |
| **Mean ± StdDev** | 732.33 ± 5.35 ms | 72.96 ± 0.84 ms | — |

> **What's happening**: Spatie caches the *global* permission/role registry (which permissions exist), but the *user's* role and permission relations are loaded via Eloquent on every `User::find()` — exactly 4 DB queries per authorization-heavy request. The Redis package keeps the full user-to-roles-to-permissions mapping in Redis, so the only DB query left is the `SELECT * FROM users` lookup itself (1 per request). The speedup is consistent at **~10x median** across all iteration counts because both strategies scale linearly — the *constant* per the iteration is what differs (4 DB queries vs 1 Redis lookup). Batch operations (`hasAnyRole`, `hasAllRoles`, `hasAnyPermission`, `hasAllPermissions`) resolve in PHP memory once the per-user relations are loaded, so they don't add queries — but they do contribute the same constant overhead to both packages.

## Screenshots

### Dashboard Overview (10 iterations)
![Benchmark Dashboard](screenshots/dashboard.png)

### Full Comparison (1 iteration)
![Full Comparison 1x](screenshots/comparison-1x.png)

### Full Comparison (50 iterations)
![Full Comparison 50x](screenshots/comparison-50x.png)

### Debugbar Integration
![Debugbar](screenshots/debugbar.png)

## What is being tested?

On each iteration the benchmark performs:

- **27 permission checks** — `hasPermissionTo()` for every seeded permission
- **4 role checks** — `hasRole()` for admin, editor, viewer, and a nonexistent role
- **2 collection calls** — `getAllPermissions()` and `getRoleNames()`

Both packages operate on the **same database tables** and the **same user data**, ensuring a fair comparison. A consistency table verifies that both packages return identical results for every check.

## Quick Start with Docker

```bash
git clone https://github.com/scabarcas17/laravel-permissions-redis-benchmark.git
cd laravel-permissions-redis-benchmark
docker compose up -d
```

Open **http://localhost:8080** in your browser.

## Quick Start (Local)

### Requirements

- PHP 8.3+
- Composer
- Redis server running on localhost:6379

### Setup

```bash
git clone https://github.com/scabarcas17/laravel-permissions-redis-benchmark.git
cd laravel-permissions-redis-benchmark

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed --seeder=BenchmarkSeeder
php artisan serve --port=8080
```

Open **http://localhost:8080** in your browser.

Use the iteration selector (1x, 5x, 10x, 25x, 50x) to see how query counts scale.

## How it works

The benchmark uses two separate Eloquent User models pointing to the same `users` table:

- **`SpatieUser`** — uses `Spatie\Permission\Traits\HasRoles`
- **`RedisUser`** — uses `Scabarcas\LaravelPermissionsRedis\Traits\HasRedisPermissions`

`DB::enableQueryLog()` captures every SQL query made by each package independently. The Redis package's cache is pre-warmed during seeding, simulating a real-world scenario where the cache is populated on user login.

## Tech Stack

- Laravel 13
- PHP 8.3+
- spatie/laravel-permission ^7.2
- scabarcas/laravel-permissions-redis **v4.0.0-beta.2** (tracked via `dev-main`)
- barryvdh/laravel-debugbar (open the Debugbar at the bottom for detailed query analysis)
- SQLite + Redis

## What this bench does not cover yet

The current harness exercises `hasPermissionTo`, `hasRole`, `hasAnyRole`, `hasAllRoles`, `hasAnyPermission`, `hasAllPermissions`, `getAllPermissions`, and `getRoleNames` against a single user. It does **not** yet exercise:

- Wildcard permission resolution (`posts.*`) — both packages support it; needs a separate scenario with non-direct assignments
- Group invalidation semantics introduced in v4 — measures mutation cost, separate harness
- The atomic warm flow (`warmUser` / `warmAll`) — single-shot mutation, not iteration-driven
- Octane request lifecycle (no Roadrunner / Swoole)
- Multiple users in parallel (single-user under load)
- Multiple DB engines (SQLite only — MySQL / Postgres would show different absolute numbers)

For concurrent-load numbers, multi-DB-engine comparisons, or Octane-specific behaviour, you'll need to extend this harness or pair it with a load generator (k6, wrk, etc.).

## License

MIT
