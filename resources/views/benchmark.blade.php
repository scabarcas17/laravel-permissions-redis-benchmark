<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permissions Benchmark</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Permissions Benchmark</h1>
        <p class="text-gray-500 mb-6">
            spatie/laravel-permission vs scabarcas/laravel-permissions-redis
        </p>

        <div class="mb-6 flex gap-2">
            @foreach([1, 5, 10, 25, 50] as $n)
                <a href="?iterations={{ $n }}"
                   class="px-4 py-2 rounded {{ $iterations == $n ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} shadow text-sm font-medium">
                    {{ $n }}x iterations
                </a>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6 border-t-4 border-orange-500">
                <h2 class="text-xl font-semibold text-orange-600 mb-4">spatie/laravel-permission</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-500">DB Queries</span>
                        <span class="text-2xl font-bold text-orange-600">{{ $spatie['queries'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Time</span>
                        <span class="text-2xl font-bold text-orange-600">{{ $spatie['time'] }} ms</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Iterations</span>
                        <span class="font-medium">{{ $iterations }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Checks per iteration</span>
                        <span class="font-medium">{{ $permissionsChecked }} perms + 4 roles + 2 collections</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 border-t-4 border-green-500">
                <h2 class="text-xl font-semibold text-green-600 mb-4">scabarcas/laravel-permissions-redis</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-500">DB Queries</span>
                        <span class="text-2xl font-bold text-green-600">{{ $redis['queries'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Time</span>
                        <span class="text-2xl font-bold text-green-600">{{ $redis['time'] }} ms</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Iterations</span>
                        <span class="font-medium">{{ $iterations }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Checks per iteration</span>
                        <span class="font-medium">{{ $permissionsChecked }} perms + 4 roles + 2 collections</span>
                    </div>
                </div>
            </div>
        </div>

        @php
            $maxQueries = max($spatie['queries'], $redis['queries'], 1);
            $savings = $spatie['queries'] > 0
                ? round((1 - $redis['queries'] / $spatie['queries']) * 100, 1)
                : 0;
        @endphp
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4">Query Comparison</h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-orange-600 font-medium">Spatie</span>
                        <span>{{ $spatie['queries'] }} queries</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-6">
                        <div class="bg-orange-500 h-6 rounded-full flex items-center justify-end pr-2 text-white text-xs font-medium"
                             style="width: {{ ($spatie['queries'] / $maxQueries) * 100 }}%; min-width: 2rem">
                            {{ $spatie['queries'] }}
                        </div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-green-600 font-medium">Redis</span>
                        <span>{{ $redis['queries'] }} queries</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-6">
                        <div class="bg-green-500 h-6 rounded-full flex items-center justify-end pr-2 text-white text-xs font-medium"
                             style="width: {{ max(($redis['queries'] / $maxQueries) * 100, 2) }}%; min-width: 2rem">
                            {{ $redis['queries'] }}
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-lg">
                @if($savings > 0)
                    <span class="text-green-600 font-bold">{{ $savings }}% less DB queries</span> with Redis package
                @elseif($savings == 0 && $spatie['queries'] == 0)
                    <span class="text-gray-500">No queries recorded</span>
                @endif
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4">Permission Check Results (consistency check)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-3">Permission</th>
                            <th class="text-center py-2 px-3">Spatie</th>
                            <th class="text-center py-2 px-3">Redis</th>
                            <th class="text-center py-2 px-3">Match</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($spatie['results'] as $perm => $spatieResult)
                            @php $redisResult = $redis['results'][$perm] ?? false; @endphp
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-3 font-mono text-xs">{{ $perm }}</td>
                                <td class="py-2 px-3 text-center">
                                    <span class="{{ $spatieResult ? 'text-green-600' : 'text-red-500' }}">
                                        {{ $spatieResult ? 'true' : 'false' }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <span class="{{ $redisResult ? 'text-green-600' : 'text-red-500' }}">
                                        {{ $redisResult ? 'true' : 'false' }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    @if($spatieResult === $redisResult)
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-orange-600 mb-3">Spatie Query Log ({{ $spatie['queries'] }})</h3>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach($spatie['queryLog'] as $i => $q)
                        <div class="bg-gray-50 rounded p-2 text-xs font-mono">
                            <span class="text-gray-400">#{{ $i + 1 }}</span>
                            <span class="text-orange-700">{{ $q['query'] }}</span>
                            <span class="text-gray-400 ml-2">{{ round($q['time'], 2) }}ms</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-green-600 mb-3">Redis Query Log ({{ $redis['queries'] }})</h3>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @forelse($redis['queryLog'] as $i => $q)
                        <div class="bg-gray-50 rounded p-2 text-xs font-mono">
                            <span class="text-gray-400">#{{ $i + 1 }}</span>
                            <span class="text-green-700">{{ $q['query'] }}</span>
                            <span class="text-gray-400 ml-2">{{ round($q['time'], 2) }}ms</span>
                        </div>
                    @empty
                        <p class="text-gray-400 text-sm">No DB queries - all resolved via Redis</p>
                    @endforelse
                </div>
            </div>
        </div>

        <p class="text-center text-gray-400 text-sm">
            Open the Debugbar below for detailed query analysis per package
        </p>
    </div>
</body>
</html>
