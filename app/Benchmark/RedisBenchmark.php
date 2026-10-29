<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\Benchmark\Contracts\PermissionBenchmark;
use App\Models\RedisUser;
use Scabarcas\LaravelPermissionsRedis\Contracts\PermissionResolverInterface;

class RedisBenchmark implements PermissionBenchmark
{
    public function label(): string
    {
        return 'scabarcas/laravel-permissions-redis';
    }

    public function color(): string
    {
        return 'green';
    }

    public function prepare(int $userId): void
    {
        // The seeder warmed the Redis SETs; a miss would warm them on the first check.
    }

    public function resetPerRequest(): void
    {
        // A fresh HTTP request carries no PHP process memory, so drop the resolver's
        // in-memory cache. Each request then re-reads the user's data from Redis.
        // This matches PHP-FPM, and Octane (where this package resets in-memory
        // state between requests). The Redis SETs themselves stay warm.
        app(PermissionResolverInterface::class)->flush();
    }

    public function run(int $userId, array $permissions, array $roles, int $iterations): array
    {
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $this->resetPerRequest();

            $user = RedisUser::find($userId);

            foreach ($permissions as $perm) {
                $has = $user->hasPermissionTo($perm);

                if ($i === 0) {
                    $results[$perm] = $has;
                }
            }

            foreach ($roles as $role) {
                $user->hasRole($role);
            }

            $user->hasAnyRole('admin', 'editor', 'viewer');
            $user->hasAllRoles('admin', 'editor');
            $user->hasAnyPermission('posts.view', 'posts.edit');
            $user->hasAllPermissions('posts.view', 'posts.edit', 'reports.export');

            $user->getAllPermissions();
            $user->getRoleNames();
        }

        return $results;
    }
}
