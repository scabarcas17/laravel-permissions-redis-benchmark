<?php

namespace App\Http\Controllers;

use App\Models\RedisUser;
use App\Models\SpatieUser;
use Illuminate\Support\Facades\DB;

class BenchmarkController extends Controller
{
    private array $permissionsToCheck = [
        'users.view', 'users.create', 'users.edit', 'users.delete',
        'posts.view', 'posts.create', 'posts.edit', 'posts.delete',
        'comments.view', 'comments.create', 'comments.edit', 'comments.delete',
        'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
        'tags.view', 'tags.create', 'tags.edit', 'tags.delete',
        'settings.view', 'settings.edit',
        'reports.view', 'reports.export',
        'dashboard.view',
        'media.upload', 'media.delete',
    ];

    public function index()
    {
        $userId = 1;
        $iterations = (int) request('iterations', 1);

        // --- SPATIE BENCHMARK ---
        DB::enableQueryLog();
        DB::flushQueryLog();

        $spatieStart = microtime(true);
        $spatieResults = [];

        for ($i = 0; $i < $iterations; $i++) {
            $spatieUser = SpatieUser::find($userId);
            foreach ($this->permissionsToCheck as $perm) {
                $result = $spatieUser->hasPermissionTo($perm);
                if ($i === 0) {
                    $spatieResults[$perm] = $result;
                }
            }
            $spatieUser->hasRole('admin');
            $spatieUser->hasRole('editor');
            $spatieUser->hasRole('viewer');
            $spatieUser->hasRole('nonexistent');
            $spatieUser->getAllPermissions();
            $spatieUser->getRoleNames();
        }

        $spatieTime = round((microtime(true) - $spatieStart) * 1000, 2);
        $spatieQueries = DB::getQueryLog();
        $spatieQueryCount = count($spatieQueries);

        // --- REDIS BENCHMARK ---
        DB::flushQueryLog();

        $redisStart = microtime(true);
        $redisResults = [];

        for ($i = 0; $i < $iterations; $i++) {
            $redisUser = RedisUser::find($userId);
            foreach ($this->permissionsToCheck as $perm) {
                $result = $redisUser->hasPermissionTo($perm);
                if ($i === 0) {
                    $redisResults[$perm] = $result;
                }
            }
            $redisUser->hasRole('admin');
            $redisUser->hasRole('editor');
            $redisUser->hasRole('viewer');
            $redisUser->hasRole('nonexistent');
            $redisUser->getAllPermissions();
            $redisUser->getRoleNames();
        }

        $redisTime = round((microtime(true) - $redisStart) * 1000, 2);
        $redisQueries = DB::getQueryLog();
        $redisQueryCount = count($redisQueries);

        DB::disableQueryLog();

        return view('benchmark', [
            'iterations' => $iterations,
            'permissionsChecked' => count($this->permissionsToCheck),
            'spatie' => [
                'queries' => $spatieQueryCount,
                'time' => $spatieTime,
                'results' => $spatieResults,
                'queryLog' => $spatieQueries,
            ],
            'redis' => [
                'queries' => $redisQueryCount,
                'time' => $redisTime,
                'results' => $redisResults,
                'queryLog' => $redisQueries,
            ],
        ]);
    }
}
