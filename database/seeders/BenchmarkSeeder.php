<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Benchmark\BenchmarkRunner;
use App\Models\SpatieUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Scabarcas\LaravelPermissionsRedis\Cache\AuthorizationCacheManager;
use Scabarcas\LaravelPermissionsRedis\Models\Permission as RedisPermission;
use Scabarcas\LaravelPermissionsRedis\Models\Role as RedisRole;
use Spatie\Permission\PermissionRegistrar;

class BenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPermissionsAndRoles();

        // Spatie maintains its own permission cache — flush it so it
        // picks up the rows created via the Redis package models.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assignUserRoles($userId);

        app(AuthorizationCacheManager::class)->warmUser($userId);

        $this->command->info(
            sprintf('Seeded: 1 user, %d permissions, 3 roles', count(BenchmarkRunner::PERMISSIONS))
        );
    }

    private function createPermissionsAndRoles(): void
    {
        foreach (BenchmarkRunner::PERMISSIONS as $perm) {
            RedisPermission::findOrCreate($perm, 'web');
        }

        $admin = RedisRole::findOrCreate('admin', 'web');
        $editor = RedisRole::findOrCreate('editor', 'web');
        $viewer = RedisRole::findOrCreate('viewer', 'web');

        $admin->syncPermissions(BenchmarkRunner::PERMISSIONS);

        $editor->syncPermissions([
            'posts.view', 'posts.create', 'posts.edit',
            'comments.view', 'comments.create', 'comments.edit',
            'categories.view', 'tags.view', 'tags.create',
            'media.upload', 'dashboard.view',
        ]);

        $viewer->syncPermissions([
            'users.view', 'posts.view', 'comments.view',
            'categories.view', 'tags.view', 'dashboard.view',
        ]);
    }

    /**
     * Assign roles via Spatie's trait because it writes model_type using
     * getMorphClass() → 'App\Models\User'.  The Redis cache manager also
     * reads with that same model type (from config 'permissions-redis.user_model'),
     * so both packages resolve the same pivot rows.
     */
    private function assignUserRoles(int $userId): void
    {
        $user = SpatieUser::find($userId);
        $user->assignRole('admin', 'editor');
        $user->givePermissionTo('reports.export');
    }
}
