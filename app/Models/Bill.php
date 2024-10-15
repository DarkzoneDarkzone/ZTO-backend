<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    // protected $timestamp_date = [
    //     'created_at' => 'datetime',
    //     'updated_at' => 'datetime',
    // ];

    // public function getCreatedAtAttribute($value)
    // {
    //     return Carbon::parse($value)->format('Y-m-d H:i:s');
    // }

    // public function getUpdatedAtAttribute($value)
    // {
    //     return Carbon::parse($value)->format('Y-m-d H:i:s');
    // }

    public function Payments()
    {
        return $this->belongsToMany(Payment::class);
    }

    public function Parcels()
    {
        return $this->hasMany(Parcel::class);
    }
}
