<?php

use App\Benchmark\BenchmarkRunner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'bench:markdown {iterations=1,10,50} {--warm=3} {--runs=10}',
    function (string $iterations, int $warm, int $runs) {
        $list = array_map('intval', array_filter(explode(',', $iterations)));
        $runner = app(BenchmarkRunner::class);

        $this->line("> Methodology: {$warm} warm-up + {$runs} measurement runs per strategy, GC reset before each run, Spatie cache flushed before warm-up.");
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

            if (count($results) < 2) {
                $this->error('Need at least 2 strategies bound in AppServiceProvider.');

                return;
            }

            [$spatie, $redis] = $results;

            $queryDelta = $spatie->queryCount > 0
                ? round((1 - $redis->queryCount / $spatie->queryCount) * 100, 1).'% fewer'
                : '—';

            $speedupP50 = $redis->timeP50 > 0
                ? round($spatie->timeP50 / $redis->timeP50, 2).'x faster'
                : '—';

            $label = $n === 1 ? '1 Iteration' : "{$n} Iterations";

            $this->line("### {$label} (27 permission checks + 4 role checks + 2 collection calls)");
            $this->newLine();
            $this->line('| Metric | spatie/laravel-permission | scabarcas/laravel-permissions-redis | Delta |');
            $this->line('|--------|:---:|:---:|:---:|');
            $this->line(sprintf(
                '| **DB Queries** | %d | %d | **%s** |',
                $spatie->queryCount,
                $redis->queryCount,
                $queryDelta,
            ));
            $this->line(sprintf(
                '| **Median (p50)** | %s ms | %s ms | **%s** |',
                number_format($spatie->timeP50, 2),
                number_format($redis->timeP50, 2),
                $speedupP50,
            ));
            $this->line(sprintf(
                '| **p95** | %s ms | %s ms | — |',
                number_format($spatie->timeP95, 2),
                number_format($redis->timeP95, 2),
            ));
            $this->line(sprintf(
                '| **p99** | %s ms | %s ms | — |',
                number_format($spatie->timeP99, 2),
                number_format($redis->timeP99, 2),
            ));
            $this->line(sprintf(
                '| **Mean ± StdDev** | %s ± %s ms | %s ± %s ms | — |',
                number_format($spatie->timeMean, 2),
                number_format($spatie->timeStdDev, 2),
                number_format($redis->timeMean, 2),
                number_format($redis->timeStdDev, 2),
            ));
            $this->newLine();
        }
    }
)->purpose('Run the benchmark and print Markdown tables with percentile stats ready to paste into README.md');
