<?php

declare(strict_types=1);

namespace App\Benchmark\Contracts;

interface PermissionBenchmark
{
    public function label(): string;

    public function color(): string;

    /**
     * Reset per-request in-memory state so each benchmark iteration models a
     * separate HTTP request (no PHP process memory carried over between requests).
     */
    public function resetPerRequest(): void;

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     * @return array<string, bool>
     */
    public function run(int $userId, array $permissions, array $roles, int $iterations): array;
}
