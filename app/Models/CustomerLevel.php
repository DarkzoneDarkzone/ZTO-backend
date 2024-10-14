<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class CustomerLevel extends Model
{
    use HasFactory, SoftDeletes;

    protected $ASC = 'asc';
    protected $DESC = 'desc';
    protected $EQUAL = 'equal';

    protected $timestamp_date = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    public function Customers()
    {
        return $this->hasMany(Customer::class);
    }
}
