<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnParcel extends Model
{
    use HasFactory, SoftDeletes;

    public function Parcel()
    {
        return $this->belongsTo(Parcel::class);
    }

    public function IncomeExpense()
    {
        return $this->belongsTo(IncomeExpense::class);
    }
}
