<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Parcel extends Model
{
    use HasFactory, SoftDeletes;

    public function Bills()
    {
        return $this->belongsTo(Bill::class);
    }

    public function ReturnParcel()
    {
        return $this->hasMany(ReturnParcel::class);
    }
}
