<?php

declare(strict_types=1);

namespace App\Providers;

use App\Benchmark\BenchmarkRunner;
use App\Benchmark\CacheRememberBenchmark;
use App\Benchmark\QueryPerCheckBenchmark;
use App\Benchmark\RedisBenchmark;
use App\Benchmark\RedisCommandCounter;
use App\Benchmark\SpatieBenchmark;
use App\Benchmark\SpatieEagerLoadBenchmark;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BenchmarkRunner::class, fn (): BenchmarkRunner => new BenchmarkRunner(
            strategies: [
                new QueryPerCheckBenchmark,
                new SpatieBenchmark,
                new SpatieEagerLoadBenchmark,
                new CacheRememberBenchmark,
                new RedisBenchmark,
            ],
            redisCounter: new RedisCommandCounter,
        ));
    }

    public function boot(): void
    {
        //
    }
}
