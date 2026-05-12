# Laravel Permissions Benchmark

A side-by-side benchmark comparing **DB query counts and wall-clock time** between [spatie/laravel-permission](https://github.com/spatie/laravel-permission) and [scabarcas/laravel-permissions-redis](https://github.com/scabarcas17/laravel-permissions-redis).

Tested against:
- `scabarcas/laravel-permissions-redis` **v4.0.0-beta.2** (resolved from `dev-main` via the VCS repository in `composer.json`)
- `spatie/laravel-permission` **^7.2**

## Methodology caveat

> Single-shot timing on local SQLite + Redis. Numbers are reproducible on the same machine but not directly comparable across hosts (CPU, OS, Redis latency vary). For production-grade numbers run the bench on a target-like environment with multiple iterations and look at the trend, not absolute ms.

## Results

Generated with `php artisan bench:markdown` against SQLite + Redis on Apple Silicon, PHP 8.4, predis client. `PermissionRegistrar::forgetCachedPermissions()` is called before each group so Spatie starts cold each time (matching per-request semantics).

### 1 Iteration (27 permission checks + 4 role checks + 2 collection calls)

| Metric | spatie/laravel-permission | scabarcas/laravel-permissions-redis | Delta |
|--------|:---:|:---:|:---:|
| **DB Queries** | 6 | 1 | **83.3% fewer** |
| **Time** | 33.52 ms | 13.07 ms | **2.56x faster** |

### 10 Iterations (27 permission checks + 4 role checks + 2 collection calls)

| Metric | spatie/laravel-permission | scabarcas/laravel-permissions-redis | Delta |
|--------|:---:|:---:|:---:|
| **DB Queries** | 42 | 10 | **76.2% fewer** |
| **Time** | 143.00 ms | 12.29 ms | **11.64x faster** |

### 50 Iterations (27 permission checks + 4 role checks + 2 collection calls)

| Metric | spatie/laravel-permission | scabarcas/laravel-permissions-redis | Delta |
|--------|:---:|:---:|:---:|
| **DB Queries** | 202 | 50 | **75.2% fewer** |
| **Time** | 679.11 ms | 61.76 ms | **11x faster** |

> **What's happening**: Spatie caches the *global* permission/role registry (which permissions exist), but the *user's* role and permission relations are loaded via Eloquent on every `User::find()` — roughly 4 DB queries per authorization-heavy request. The Redis package keeps the full user-to-roles-to-permissions mapping in Redis, so the only DB query left is the `SELECT * FROM users` lookup itself (1 per request). The wall-clock delta grows with iterations because Redis lookups are near-constant time while Spatie's relation hydration scales with each new user instance.

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

The current harness only exercises `hasPermissionTo`, `hasRole`, `getAllPermissions`, and `getRoleNames` against a single user. It does **not** yet exercise:

- The expanded `PermissionRepositoryInterface` from v4 (batch role checks like `userHasAnyRole`, `userHasAllRoles`)
- Wildcard permission resolution (`posts.*`)
- Group invalidation semantics introduced in v4
- The atomic warm flow (`warmUser` / `warmAll`)
- Octane request lifecycle (no Roadrunner / Swoole)
- Multiple users in parallel (single-shot, single-user)

If you want apples-to-apples production numbers (p50/p95/p99 under concurrency, multiple DB engines, Octane), this bench is a starting point — not the final word.

## License

MIT
