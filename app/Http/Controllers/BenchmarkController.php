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
        $iterations = (int) $request->query('iterations', 1);

        return view('benchmark', [
            'iterations' => $iterations,
            'permissionsChecked' => count(BenchmarkRunner::PERMISSIONS),
            'results' => $this->runner->execute(userId: 1, iterations: $iterations),
        ]);
    }
}
