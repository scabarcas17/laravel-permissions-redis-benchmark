<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\Benchmark\Contracts\PermissionBenchmark;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class BenchmarkRunner
{
    public const int DEFAULT_WARM_UP_RUNS = 3;

    public const int DEFAULT_MEASUREMENT_RUNS = 10;

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
        private readonly RedisCommandCounter $redisCounter,
    ) {}

    /** @return list<BenchmarkResult> */
    public function execute(
        int $userId,
        int $iterations,
        int $warmUpRuns = self::DEFAULT_WARM_UP_RUNS,
        int $measurementRuns = self::DEFAULT_MEASUREMENT_RUNS,
    ): array {
        $results = [];

        foreach ($this->strategies as $strategy) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $strategy->prepare($userId);

            for ($w = 0; $w < $warmUpRuns; $w++) {
                $strategy->run($userId, self::PERMISSIONS, self::ROLES, $iterations);
            }

            // One untimed run counts what the strategy sends to the database
            // and to Redis; the timed runs below have every log switched off.
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->redisCounter->start();

            $lastResults = $strategy->run($userId, self::PERMISSIONS, self::ROLES, $iterations);

            $redisCommandCount = $this->redisCounter->stop();
            $queryLog = DB::getQueryLog();
            DB::disableQueryLog();
            DB::flushQueryLog();

            $times = [];

            for ($m = 0; $m < $measurementRuns; $m++) {
                gc_collect_cycles();

                $start = microtime(true);
                $strategy->run($userId, self::PERMISSIONS, self::ROLES, $iterations);
                $times[] = (microtime(true) - $start) * 1000;
            }

            $results[] = new BenchmarkResult(
                label: $strategy->label(),
                color: $strategy->color(),
                timeP50: self::percentile($times, 50),
                timeP95: self::percentile($times, 95),
                timeP99: self::percentile($times, 99),
                timeMean: array_sum($times) / count($times),
                timeStdDev: self::stddev($times),
                queryCount: count($queryLog),
                redisCommandCount: $redisCommandCount,
                warmUpRuns: $warmUpRuns,
                measurementRuns: $measurementRuns,
                rawTimesMs: $times,
                permissionResults: $lastResults,
                queryLog: $queryLog,
            );
        }

        return $results;
    }

    /** @param list<float> $values */
    private static function percentile(array $values, int $p): float
    {
        sort($values);
        $index = (int) ceil($p / 100 * count($values)) - 1;

        return $values[max(0, $index)];
    }

    /** @param list<float> $values */
    private static function stddev(array $values): float
    {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $squaredDiffs = array_map(fn (float $v): float => ($v - $mean) ** 2, $values);

        return sqrt(array_sum($squaredDiffs) / ($count - 1));
    }
}
