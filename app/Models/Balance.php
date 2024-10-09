<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Balance extends Model
{
    use HasFactory;

    public function Payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function IncomeExpense()
    {
        return $this->belongsTo(IncomeExpense::class);
    }
}
