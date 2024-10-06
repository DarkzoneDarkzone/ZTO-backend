<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class CustomerLevel extends Model
{
    use HasFactory, SoftDeletes;

    protected $ASC = 'asc';
    protected $DESC = 'desc';
    protected $EQUAL = 'equal';

    public function Customers()
    {
        return $this->hasMany(Customer::class);
    }
}
