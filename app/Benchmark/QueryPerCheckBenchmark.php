<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\Benchmark\Contracts\PermissionBenchmark;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * No package and no cache: every check asks the database, the way a
 * hand-rolled Gate::before() or policy usually starts. Nothing to
 * invalidate, one query per check.
 */
class QueryPerCheckBenchmark implements PermissionBenchmark
{
    private const string GUARD = 'web';

    public function label(): string
    {
        return 'Query per check (hand-rolled Gate)';
    }

    public function color(): string
    {
        return 'red';
    }

    public function prepare(int $userId): void
    {
        // Nothing is cached anywhere.
    }

    public function resetPerRequest(): void
    {
        // Nothing is kept between checks, let alone between requests.
    }

    public function run(int $userId, array $permissions, array $roles, int $iterations): array
    {
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $this->resetPerRequest();

            $id = (int) User::findOrFail($userId)->getKey();

            foreach ($permissions as $perm) {
                $has = $this->hasPermission($id, $perm);

                if ($i === 0) {
                    $results[$perm] = $has;
                }
            }

            foreach ($roles as $role) {
                $this->hasRole($id, $role);
            }

            // The batch checks stop at the first answer that settles them,
            // as the packages' do.
            collect(['admin', 'editor', 'viewer'])->contains(fn (string $role): bool => $this->hasRole($id, $role));
            collect(['admin', 'editor'])->every(fn (string $role): bool => $this->hasRole($id, $role));
            collect(['posts.view', 'posts.edit'])->contains(fn (string $perm): bool => $this->hasPermission($id, $perm));
            collect(['posts.view', 'posts.edit', 'reports.export'])->every(fn (string $perm): bool => $this->hasPermission($id, $perm));

            $this->permissionNames($id);
            $this->roleNames($id);
        }

        return $results;
    }

    /**
     * Held directly or through a role, in one EXISTS query.
     */
    private function hasPermission(int $userId, string $permission): bool
    {
        return DB::table('permissions as p')
            ->where('p.name', $permission)
            ->where('p.guard_name', self::GUARD)
            ->where(fn (Builder $query) => $query
                ->whereExists(fn (Builder $direct) => $direct
                    ->from('model_has_permissions as mhp')
                    ->whereColumn('mhp.permission_id', 'p.id')
                    ->where('mhp.model_type', User::class)
                    ->where('mhp.model_id', $userId))
                ->orWhereExists(fn (Builder $viaRole) => $viaRole
                    ->from('role_has_permissions as rhp')
                    ->join('model_has_roles as mhr', 'mhr.role_id', '=', 'rhp.role_id')
                    ->whereColumn('rhp.permission_id', 'p.id')
                    ->where('mhr.model_type', User::class)
                    ->where('mhr.model_id', $userId)))
            ->exists();
    }

    private function hasRole(int $userId, string $role): bool
    {
        return DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', User::class)
            ->where('mhr.model_id', $userId)
            ->where('r.name', $role)
            ->where('r.guard_name', self::GUARD)
            ->exists();
    }

    /**
     * Direct and role permissions in one UNION query.
     *
     * @return list<string>
     */
    private function permissionNames(int $userId): array
    {
        $viaRoles = DB::table('permissions as p')
            ->join('role_has_permissions as rhp', 'rhp.permission_id', '=', 'p.id')
            ->join('model_has_roles as mhr', 'mhr.role_id', '=', 'rhp.role_id')
            ->where('mhr.model_type', User::class)
            ->where('mhr.model_id', $userId)
            ->select('p.name');

        return DB::table('permissions as p')
            ->join('model_has_permissions as mhp', 'mhp.permission_id', '=', 'p.id')
            ->where('mhp.model_type', User::class)
            ->where('mhp.model_id', $userId)
            ->select('p.name')
            ->union($viaRoles)
            ->pluck('name')
            ->all();
    }

    /** @return list<string> */
    private function roleNames(int $userId): array
    {
        return DB::table('roles as r')
            ->join('model_has_roles as mhr', 'mhr.role_id', '=', 'r.id')
            ->where('mhr.model_type', User::class)
            ->where('mhr.model_id', $userId)
            ->pluck('r.name')
            ->all();
    }
}
