<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Benchmark\BenchmarkRunner;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BenchmarkController extends Controller
{
    public function __construct(
        private readonly BenchmarkRunner $runner,
    ) {}

    public function __invoke(Request $request): View
    {
        $iterations = max(1, (int) $request->query('iterations', 1));
        $warmUpRuns = max(0, (int) $request->query('warm', BenchmarkRunner::DEFAULT_WARM_UP_RUNS));
        $measurementRuns = max(1, (int) $request->query('runs', BenchmarkRunner::DEFAULT_MEASUREMENT_RUNS));

        return view('benchmark', [
            'iterations' => $iterations,
            'warmUpRuns' => $warmUpRuns,
            'measurementRuns' => $measurementRuns,
            'permissionsChecked' => count(BenchmarkRunner::PERMISSIONS),
            'results' => $this->runner->execute(
                userId: 1,
                iterations: $iterations,
                warmUpRuns: $warmUpRuns,
                measurementRuns: $measurementRuns,
            ),
        ]);
    }
}
