<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\Models\SpatieUser;

/**
 * Spatie with the user's relations loaded in one place right after the
 * lookup, as an "eager load on auth" middleware does:
 * $request->user()?->loadMissing(['roles.permissions', 'permissions']).
 */
class SpatieEagerLoadBenchmark extends SpatieBenchmark
{
    public function label(): string
    {
        return 'spatie/laravel-permission + eager loading';
    }

    public function color(): string
    {
        return 'blue';
    }

    protected function findUser(int $userId): SpatieUser
    {
        $user = parent::findUser($userId);
        $user->loadMissing(['roles.permissions', 'permissions']);

        return $user;
    }
}
