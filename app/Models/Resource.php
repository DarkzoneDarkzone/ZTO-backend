<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Resource extends Model
{
    use HasFactory, SoftDeletes;

    public function Role()
    {
        return $this->belongsToMany(Role::class);
    }
}
