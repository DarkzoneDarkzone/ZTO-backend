<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class BillPayment extends Model
{
    use HasFactory, SoftDeletes;

    // public function Payments()
    // {
    //     return $this->belongsToMany(Payment::class);
    // }

    // public function Bills()
    // {
    //     return $this->belongsToMany(Bill::class);
    // }
}
