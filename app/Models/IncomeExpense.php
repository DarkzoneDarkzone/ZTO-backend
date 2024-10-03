<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class IncomeExpense extends Model
{
    use HasFactory, SoftDeletes;

    public function balance()
    {
        return $this->hasOne(Balance::class);
    }
    
}
