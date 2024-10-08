<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    public function Payments()
    {
        return $this->belongsToMany(Payment::class)->withTimestamps();
    }

    public function Parcels()
    {
        return $this->hasMany(Parcel::class);
    }
}
