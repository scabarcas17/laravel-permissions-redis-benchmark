<?php

use App\Benchmark\BenchmarkRunner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'bench:markdown {iterations=1,10,50} {--warm=3} {--runs=10}',
    function (string $iterations, int $warm, int $runs) {
        $list = array_map('intval', array_filter(explode(',', $iterations)));
        $runner = app(BenchmarkRunner::class);

        $this->line("> Methodology: per strategy, {$warm} warm-up runs, one untimed run that counts SQL queries and Redis commands, then {$runs} timed runs with logging off, GC reset before each. Each iteration models a separate request: a fresh user model and empty in-process caches, while Spatie's registry, the Cache::remember() entries and the Redis SETs stay warm.");
        $this->line(sprintf(
            '> Environment: PHP %s, %s database, Redis client %s.',
            PHP_VERSION,
            config('database.default'),
            config('database.redis.client'),
        ));
        $this->newLine();

        foreach ($list as $n) {
            if ($n <= 0) {
                continue;
            }

            $results = $runner->execute(
                userId: 1,
                iterations: $n,
                warmUpRuns: $warm,
                measurementRuns: $runs,
            );

            $label = $n === 1 ? '1 Iteration' : "{$n} Iterations";

            $this->line("### {$label} (27 permission checks + 4 role checks + 4 batch ops + 2 collection calls)");
            $this->newLine();
            $this->line('| Strategy | DB queries | Redis commands | Median (p50) | p95 | p99 | Mean ± StdDev |');
            $this->line('|----------|:---:|:---:|:---:|:---:|:---:|:---:|');

            foreach ($results as $result) {
                $this->line(sprintf(
                    '| %s | %d | %d | %s ms | %s ms | %s ms | %s ± %s ms |',
                    $result->label,
                    $result->queryCount,
                    $result->redisCommandCount,
                    number_format($result->timeP50, 2),
                    number_format($result->timeP95, 2),
                    number_format($result->timeP99, 2),
                    number_format($result->timeMean, 2),
                    number_format($result->timeStdDev, 2),
                ));
            }

            $this->newLine();

            $reference = $results[0];

            foreach ($results as $result) {
                if ($result->permissionResults !== $reference->permissionResults) {
                    $this->error("{$result->label} answered differently from {$reference->label}.");
                }
            }
        }
    }
)->purpose('Run the benchmark and print Markdown tables with percentile stats ready to paste into README.md');

Artisan::command(
    'bench:latency {ms : Delay added to every response from MySQL and Redis, in milliseconds; 0 removes it}',
    function (int $ms) {
        $api = rtrim((string) config('services.toxiproxy.url'), '/');

        foreach (['mysql', 'redis'] as $proxy) {
            // Missing when no delay was set before; a 404 here is expected.
            Http::delete("{$api}/proxies/{$proxy}/toxics/latency");

            if ($ms > 0) {
                Http::post("{$api}/proxies/{$proxy}/toxics", [
                    'name' => 'latency',
                    'type' => 'latency',
                    'stream' => 'downstream',
                    'toxicity' => 1.0,
                    'attributes' => ['latency' => $ms, 'jitter' => 0],
                ])->throw();
            }
        }

        $this->info($ms > 0
            ? "Every response from MySQL and Redis now arrives {$ms} ms later."
            : 'No added latency on MySQL and Redis.');
    }
)->purpose('Set the latency toxiproxy adds in front of MySQL and Redis (docker-compose.latency.yml)');
