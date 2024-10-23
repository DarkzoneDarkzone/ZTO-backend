<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class RoleResource extends Model
{
    use HasFactory, SoftDeletes;

    public function Resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }
}
