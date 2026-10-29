<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\Benchmark\Contracts\PermissionBenchmark;
use App\Models\SpatieUser;
use Illuminate\Support\Facades\Cache;

/**
 * Spatie resolves the user once, the role and permission names are kept per
 * user in the Redis cache store, and checks are answered from those arrays:
 * one cache read per request while the entry is warm. Keeping the entry in
 * step with role changes is left to the application, as it is in practice.
 */
class CacheRememberBenchmark implements PermissionBenchmark
{
    public function label(): string
    {
        return 'spatie/laravel-permission + Cache::remember()';
    }

    public function color(): string
    {
        return 'purple';
    }

    public function prepare(int $userId): void
    {
        // An entry left by an earlier execution may predate a reseed.
        Cache::store('redis')->forget($this->key($userId));
    }

    public function resetPerRequest(): void
    {
        // The cache entry stays warm between requests; nothing else is kept.
    }

    public function run(int $userId, array $permissions, array $roles, int $iterations): array
    {
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $this->resetPerRequest();

            $user = SpatieUser::findOrFail($userId);

            /** @var array{roles: list<string>, permissions: list<string>} $authz */
            $authz = Cache::store('redis')->remember($this->key($userId), now()->addHour(), fn (): array => [
                'roles' => $user->getRoleNames()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->all(),
            ]);

            $can = fn (string $permission): bool => in_array($permission, $authz['permissions'], true);
            $is = fn (string $role): bool => in_array($role, $authz['roles'], true);

            foreach ($permissions as $perm) {
                $has = $can($perm);

                if ($i === 0) {
                    $results[$perm] = $has;
                }
            }

            foreach ($roles as $role) {
                $is($role);
            }

            collect(['admin', 'editor', 'viewer'])->contains($is);
            collect(['admin', 'editor'])->every($is);
            collect(['posts.view', 'posts.edit'])->contains($can);
            collect(['posts.view', 'posts.edit', 'reports.export'])->every($can);

            // getAllPermissions() and getRoleNames() are the arrays themselves.
            $authz['permissions'];
            $authz['roles'];
        }

        return $results;
    }

    private function key(int $userId): string
    {
        return "authz:user:{$userId}";
    }
}
