<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BenchmarkUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class SpatieUser extends Authenticatable
{
    use BenchmarkUser;
    use HasRoles;

    protected $guard_name = 'web';
}
