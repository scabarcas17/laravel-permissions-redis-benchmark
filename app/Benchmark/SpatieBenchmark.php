<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\Benchmark\Contracts\PermissionBenchmark;
use App\Models\SpatieUser;

class SpatieBenchmark implements PermissionBenchmark
{
    public function label(): string
    {
        return 'spatie/laravel-permission';
    }

    public function color(): string
    {
        return 'orange';
    }

    public function prepare(int $userId): void
    {
        // The runner flushes Spatie's registry before every strategy's warm-up.
    }

    public function resetPerRequest(): void
    {
        // A fresh HTTP request gets a fresh user model (the lookup below) while the
        // permission registry stays warm in the cache store, which is how Spatie
        // behaves per request with caching enabled. Nothing extra to reset here,
        // and keeping the registry in memory is, if anything, favorable to Spatie.
    }

    public function run(int $userId, array $permissions, array $roles, int $iterations): array
    {
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $this->resetPerRequest();

            $user = $this->findUser($userId);

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
            $user->hasAllRoles(['admin', 'editor']);
            $user->hasAnyPermission('posts.view', 'posts.edit');
            $user->hasAllPermissions('posts.view', 'posts.edit', 'reports.export');

            $user->getAllPermissions();
            $user->getRoleNames();
        }

        return $results;
    }

    protected function findUser(int $userId): SpatieUser
    {
        return SpatieUser::findOrFail($userId);
    }
}
