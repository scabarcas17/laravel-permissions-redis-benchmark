<?php

declare(strict_types=1);

namespace App\Benchmark\Contracts;

interface PermissionBenchmark
{
    public function label(): string;

    public function color(): string;

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     * @return array<string, bool>
     */
    public function run(int $userId, array $permissions, array $roles, int $iterations): array;
}
