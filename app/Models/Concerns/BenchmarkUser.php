<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;

/**
 * Shared configuration for user models in the benchmark.
 *
 * Both SpatieUser and RedisUser point to the same `users` table and
 * resolve to the base User morph type so that pivot rows written by
 * one package are visible to the other.
 */
trait BenchmarkUser
{
    public function initializeBenchmarkUser(): void
    {
        $this->table = 'users';
    }

    public function getMorphClass(): string
    {
        return User::class;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
