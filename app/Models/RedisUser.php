<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BenchmarkUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Scabarcas\LaravelPermissionsRedis\Traits\HasRedisPermissions;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class RedisUser extends Authenticatable
{
    use BenchmarkUser;
    use HasRedisPermissions;
}
