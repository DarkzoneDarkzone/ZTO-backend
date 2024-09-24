<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Role extends Model
{
    use HasFactory, SoftDeletes;

    public function Resources()
    {
        return $this->belongsToMany(Resource::class, 'role_resources', 'role_id', 'resource_id');
    }
}
