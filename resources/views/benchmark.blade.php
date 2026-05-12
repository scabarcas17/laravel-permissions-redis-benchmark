<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permissions Benchmark</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    @php
        $colorMap = [
            'orange' => ['border' => 'border-orange-500', 'text' => 'text-orange-600', 'bg' => 'bg-orange-500', 'code' => 'text-orange-700'],
            'green'  => ['border' => 'border-green-500',  'text' => 'text-green-600',  'bg' => 'bg-green-500',  'code' => 'text-green-700'],
            'blue'   => ['border' => 'border-blue-500',   'text' => 'text-blue-600',   'bg' => 'bg-blue-500',   'code' => 'text-blue-700'],
            'purple' => ['border' => 'border-purple-500', 'text' => 'text-purple-600', 'bg' => 'bg-purple-500', 'code' => 'text-purple-700'],
        ];

        $maxQueries = max(1, ...array_map(fn ($r) => $r->queryCount, $results));
        $maxTime = max(0.01, ...array_map(fn ($r) => $r->timeP50, $results));
        $reference = $results[0] ?? null;
    @endphp

    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Permissions Benchmark</h1>
        <p class="text-gray-500 mb-4">
            {{ collect($results)->pluck('label')->join(' vs ') }}
        </p>

        {{-- Methodology pill --}}
        @if($reference)
            <div class="mb-6 inline-flex items-center gap-2 px-3 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full border border-indigo-200">
                <span>{{ $reference->warmUpRuns }} warm-up + {{ $reference->measurementRuns }} measurement runs per strategy</span>
                <span class="text-indigo-300">·</span>
                <span>GC reset before each run</span>
                <span class="text-indigo-300">·</span>
                <span>Spatie cache flushed before warm-up</span>
            </div>
        @endif

        {{-- Iteration selector --}}
        <div class="mb-6 flex gap-2">
            @foreach([1, 5, 10, 25, 50] as $n)
                <a href="?iterations={{ $n }}"
                   class="px-4 py-2 rounded {{ $iterations == $n ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} shadow text-sm font-medium">
                    {{ $n }}x iterations
                </a>
            @endforeach
        </div>

        {{-- Result cards --}}
        <div class="grid grid-cols-1 md:grid-cols-{{ count($results) }} gap-6 mb-8">
            @foreach($results as $result)
                @php $c = $colorMap[$result->color] ?? $colorMap['blue']; @endphp
                <div class="bg-white rounded-xl shadow-md p-6 border-t-4 {{ $c['border'] }}">
                    <h2 class="text-xl font-semibold {{ $c['text'] }} mb-4">{{ $result->label }}</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500">DB Queries</span>
                            <span class="text-2xl font-bold {{ $c['text'] }}">{{ $result->queryCount }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Median (p50)</span>
                            <span class="text-2xl font-bold {{ $c['text'] }}">{{ number_format($result->timeP50, 2) }} ms</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">p95 / p99</span>
                            <span class="font-mono text-gray-600">{{ number_format($result->timeP95, 2) }} / {{ number_format($result->timeP99, 2) }} ms</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Mean ± StdDev</span>
                            <span class="font-mono text-gray-600">{{ number_format($result->timeMean, 2) }} ± {{ number_format($result->timeStdDev, 2) }} ms</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Iterations / Runs</span>
                            <span class="font-medium">{{ $iterations }} × {{ $result->measurementRuns }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Checks per iteration</span>
                            <span class="font-medium">{{ $permissionsChecked }} perms + 4 roles + 4 batch + 2 collections</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Query comparison bars --}}
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4">Query Comparison</h3>
            <div class="space-y-4">
                @foreach($results as $result)
                    @php $c = $colorMap[$result->color] ?? $colorMap['blue']; @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="{{ $c['text'] }} font-medium">{{ $result->label }}</span>
                            <span>{{ $result->queryCount }} queries</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-6">
                            <div class="{{ $c['bg'] }} h-6 rounded-full flex items-center justify-end pr-2 text-white text-xs font-medium"
                                 style="width: {{ max(($result->queryCount / $maxQueries) * 100, 2) }}%; min-width: 2rem">
                                {{ $result->queryCount }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($reference && count($results) >= 2)
                @php
                    $savings = $reference->queryCount > 0
                        ? round((1 - $results[1]->queryCount / $reference->queryCount) * 100, 1)
                        : 0;
                @endphp
                <p class="mt-4 text-lg">
                    @if($savings > 0)
                        <span class="text-green-600 font-bold">{{ $savings }}% less DB queries</span>
                        with {{ $results[1]->label }}
                    @elseif($reference->queryCount === 0)
                        <span class="text-gray-500">No queries recorded</span>
                    @endif
                </p>
            @endif
        </div>

        {{-- Time comparison bars (median p50) --}}
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold mb-1">Time Comparison <span class="text-sm font-normal text-gray-400">(median across runs)</span></h3>
            <div class="space-y-4 mt-4">
                @foreach($results as $result)
                    @php $c = $colorMap[$result->color] ?? $colorMap['blue']; @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="{{ $c['text'] }} font-medium">{{ $result->label }}</span>
                            <span>{{ number_format($result->timeP50, 2) }} ms <span class="text-gray-400">(p95 {{ number_format($result->timeP95, 2) }}, p99 {{ number_format($result->timeP99, 2) }})</span></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-6">
                            <div class="{{ $c['bg'] }} h-6 rounded-full flex items-center justify-end pr-2 text-white text-xs font-medium"
                                 style="width: {{ max(($result->timeP50 / $maxTime) * 100, 2) }}%; min-width: 2rem">
                                {{ number_format($result->timeP50, 2) }} ms
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($reference && count($results) >= 2)
                @php
                    $timeSavings = $reference->timeP50 > 0
                        ? round((1 - $results[1]->timeP50 / $reference->timeP50) * 100, 1)
                        : 0;
                    $speedup = $results[1]->timeP50 > 0
                        ? round($reference->timeP50 / $results[1]->timeP50, 2)
                        : 0;
                @endphp
                <p class="mt-4 text-lg">
                    @if($timeSavings > 0)
                        <span class="text-green-600 font-bold">{{ $speedup }}x faster</span>
                        ({{ $timeSavings }}% less median time) with {{ $results[1]->label }}
                    @elseif($reference->timeP50 == 0)
                        <span class="text-gray-500">No time recorded</span>
                    @endif
                </p>
            @endif
        </div>

        {{-- Consistency check --}}
        @if($reference)
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold mb-4">Permission Check Results (consistency check)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 px-3">Permission</th>
                                @foreach($results as $result)
                                    <th class="text-center py-2 px-3">{{ $result->label }}</th>
                                @endforeach
                                <th class="text-center py-2 px-3">Match</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reference->permissionResults as $perm => $referenceValue)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-2 px-3 font-mono text-xs">{{ $perm }}</td>
                                    @foreach($results as $result)
                                        @php $val = $result->permissionResults[$perm] ?? false; @endphp
                                        <td class="py-2 px-3 text-center">
                                            <span class="{{ $val ? 'text-green-600' : 'text-red-500' }}">
                                                {{ $val ? 'true' : 'false' }}
                                            </span>
                                        </td>
                                    @endforeach
                                    <td class="py-2 px-3 text-center">
                                        @php
                                            $allMatch = collect($results)->every(
                                                fn ($r) => ($r->permissionResults[$perm] ?? false) === $referenceValue
                                            );
                                        @endphp
                                        @if($allMatch)
                                            <span class="text-green-600 font-bold">OK</span>
                                        @else
                                            <span class="text-red-600 font-bold">MISMATCH</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Query logs --}}
        <div class="grid grid-cols-1 md:grid-cols-{{ count($results) }} gap-6 mb-8">
            @foreach($results as $result)
                @php $c = $colorMap[$result->color] ?? $colorMap['blue']; @endphp
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold {{ $c['text'] }} mb-3">
                        {{ $result->label }} Query Log <span class="text-sm font-normal text-gray-400">({{ $result->queryCount }} per run)</span>
                    </h3>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @forelse($result->queryLog as $i => $q)
                            <div class="bg-gray-50 rounded p-2 text-xs font-mono">
                                <span class="text-gray-400">#{{ $i + 1 }}</span>
                                <span class="{{ $c['code'] }}">{{ $q['query'] }}</span>
                                <span class="text-gray-400 ml-2">{{ round($q['time'], 2) }}ms</span>
                            </div>
                        @empty
                            <p class="text-gray-400 text-sm">No DB queries - all resolved via Redis</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-center text-gray-400 text-sm">
            Open the Debugbar below for detailed query analysis per package
        </p>
    </div>
</body>
</html>
