<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Balance extends Model
{
    use HasFactory;

    // protected $timestamp_date = [
    //     'created_at' => 'datetime',
    //     'updated_at' => 'datetime',
    // ];

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    public function Payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function IncomeExpense()
    {
        return $this->belongsTo(IncomeExpense::class);
    }
}
