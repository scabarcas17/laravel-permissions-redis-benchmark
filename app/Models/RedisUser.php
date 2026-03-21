<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Scabarcas\LaravelPermissionsRedis\Traits\HasRedisPermissions;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class RedisUser extends Authenticatable
{
    use HasRedisPermissions;

    protected $table = 'users';

    public function getMorphClass(): string
    {
        return User::class;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
