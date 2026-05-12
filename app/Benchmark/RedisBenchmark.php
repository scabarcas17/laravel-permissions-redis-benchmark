<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\Benchmark\Contracts\PermissionBenchmark;
use App\Models\RedisUser;

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

    public function run(int $userId, array $permissions, array $roles, int $iterations): array
    {
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
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
