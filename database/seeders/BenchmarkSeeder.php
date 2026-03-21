<?php

namespace Database\Seeders;

use App\Models\RedisUser;
use App\Models\SpatieUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Scabarcas\LaravelPermissionsRedis\Cache\AuthorizationCacheManager;
use Scabarcas\LaravelPermissionsRedis\Models\Permission as RedisPermission;
use Scabarcas\LaravelPermissionsRedis\Models\Role as RedisRole;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

class BenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        $user = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissions = [
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

        foreach ($permissions as $perm) {
            SpatiePermission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        $admin = SpatieRole::create(['name' => 'admin', 'guard_name' => 'web']);
        $editor = SpatieRole::create(['name' => 'editor', 'guard_name' => 'web']);
        $viewer = SpatieRole::create(['name' => 'viewer', 'guard_name' => 'web']);

        $admin->syncPermissions(SpatiePermission::all());

        $editor->syncPermissions(SpatiePermission::whereIn('name', [
            'posts.view', 'posts.create', 'posts.edit',
            'comments.view', 'comments.create', 'comments.edit',
            'categories.view', 'tags.view', 'tags.create',
            'media.upload', 'dashboard.view',
        ])->get());

        $viewer->syncPermissions(SpatiePermission::whereIn('name', [
            'users.view', 'posts.view', 'comments.view',
            'categories.view', 'tags.view', 'dashboard.view',
        ])->get());

        $spatieUser = SpatieUser::find($user);
        $spatieUser->assignRole('admin', 'editor');

        $spatieUser->givePermissionTo('reports.export');

        $redisUser = RedisUser::find($user);
        app(AuthorizationCacheManager::class)->warmUser($user);

        $this->command->info("Seeded: 1 user, " . count($permissions) . " permissions, 3 roles");
    }
}
