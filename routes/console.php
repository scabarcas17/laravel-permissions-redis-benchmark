<?php

use App\Benchmark\BenchmarkRunner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bench:markdown {iterations=1,10,50}', function (string $iterations) {
    $list = array_map('intval', array_filter(explode(',', $iterations)));
    $runner = app(BenchmarkRunner::class);

    foreach ($list as $n) {
        if ($n <= 0) {
            continue;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $results = $runner->execute(userId: 1, iterations: $n);

        if (count($results) < 2) {
            $this->error('Need at least 2 strategies bound in AppServiceProvider.');

            return;
        }

        [$spatie, $redis] = $results;

        $queryDelta = $spatie->queryCount > 0
            ? round((1 - $redis->queryCount / $spatie->queryCount) * 100, 1).'% fewer'
            : '—';

        $timeDelta = $redis->timeMs > 0 && $spatie->timeMs > 0
            ? round($spatie->timeMs / $redis->timeMs, 2).'x faster'
            : '—';

        $label = $n === 1 ? '1 Iteration' : "{$n} Iterations";

        $this->newLine();
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
            '| **Time** | %s ms | %s ms | **%s** |',
            number_format($spatie->timeMs, 2),
            number_format($redis->timeMs, 2),
            $timeDelta,
        ));
    }
})->purpose('Run the benchmark and print Markdown tables ready to paste into README.md');
