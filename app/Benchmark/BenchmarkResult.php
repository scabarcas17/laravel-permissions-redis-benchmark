<?php

declare(strict_types=1);

namespace App\Benchmark;

readonly class BenchmarkResult
{
    /**
     * @param  array<string, bool>  $permissionResults
     * @param  list<array{query: string, bindings: array<mixed>, time: float}>  $queryLog
     */
    public function __construct(
        public string $label,
        public string $color,
        public float $timeMs,
        public int $queryCount,
        public array $permissionResults,
        public array $queryLog,
    ) {}
}
