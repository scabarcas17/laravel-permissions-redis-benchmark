<?php

declare(strict_types=1);

namespace App\Benchmark;

readonly class BenchmarkResult
{
    /**
     * @param  array<string, bool>  $permissionResults
     * @param  list<array{query: string, bindings: array<mixed>, time: float}>  $queryLog
     * @param  list<float>  $rawTimesMs  per-measurement-run wall-clock times (ms)
     */
    public function __construct(
        public string $label,
        public string $color,
        public float $timeP50,
        public float $timeP95,
        public float $timeP99,
        public float $timeMean,
        public float $timeStdDev,
        public int $queryCount,
        public int $warmUpRuns,
        public int $measurementRuns,
        public array $rawTimesMs,
        public array $permissionResults,
        public array $queryLog,
    ) {}
}
