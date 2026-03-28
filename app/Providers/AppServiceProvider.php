<?php

declare(strict_types=1);

namespace App\Providers;

use App\Benchmark\BenchmarkRunner;
use App\Benchmark\RedisBenchmark;
use App\Benchmark\SpatieBenchmark;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BenchmarkRunner::class, fn (): BenchmarkRunner => new BenchmarkRunner([
            new SpatieBenchmark(),
            new RedisBenchmark(),
        ]));
    }

    public function boot(): void
    {
        //
    }
}
