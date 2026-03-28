<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\Benchmark\Contracts\PermissionBenchmark;
use Illuminate\Support\Facades\DB;

class BenchmarkRunner
{
    public const array PERMISSIONS = [
        'users.view', 'users.create', 'users.edit', 'users.delete',
        'posts.view', 'posts.create', 'posts.edit', 'posts.delete',
        'comments.view', 'comments.create', 'comments.edit', 'comments.delete',
        'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
        'tags.view', 'tags.create', 'tags.edit', 'tags.delete',
        'settings.view', 'settings.edit',
        'reports.view', 'reports.export',
        'dashboard.view',
        'media.upload', 'media.delete',
    ];

    public const array ROLES = ['admin', 'editor', 'viewer', 'nonexistent'];

    /** @param list<PermissionBenchmark> $strategies */
    public function __construct(
        private readonly array $strategies,
    ) {}

    /** @return list<BenchmarkResult> */
    public function execute(int $userId, int $iterations): array
    {
        DB::enableQueryLog();

        $results = [];

        foreach ($this->strategies as $strategy) {
            DB::flushQueryLog();

            $start = microtime(true);
            $permissionResults = $strategy->run($userId, self::PERMISSIONS, self::ROLES, $iterations);
            $timeMs = round((microtime(true) - $start) * 1000, 2);

            $queryLog = DB::getQueryLog();

            $results[] = new BenchmarkResult(
                label: $strategy->label(),
                color: $strategy->color(),
                timeMs: $timeMs,
                queryCount: count($queryLog),
                permissionResults: $permissionResults,
                queryLog: $queryLog,
            );
        }

        DB::disableQueryLog();

        return $results;
    }
}
