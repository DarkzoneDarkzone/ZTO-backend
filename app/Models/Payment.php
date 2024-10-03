<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;


class Payment extends Model
{
    use HasFactory, SoftDeletes;

    public function Bills() : BelongsToMany
    {
        return $this->belongsToMany(Bill::class);
    }

    public function balance()
    {
        return $this->hasOne(Balance::class);
    }
}
