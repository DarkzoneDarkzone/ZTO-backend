<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('customer_levels')->insert([
            [
                "id" => 1,
                "name" => "level 1",
                "description" => "defult",
                "rate" => 25.00,
                "active" => true,
                "created_by" => null,

            ],
            [
                "id" => 2,
                "name" => "level 2",
                "description" => "defult",
                "rate" => 20.00,
                "active" => true,
                "created_by" => null,
            ]

        ]);

        DB::table('customers')->insert([
            [
                "id" => 1,
                "name" => "cutomer no.1",
                "phone" => "0912345678",
                "address" => "9/90",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => null,
            ],
            [
                "id" => 2,
                "name" => "cutomer no.2",
                "phone" => "0987654321",
                "address" => "8/80",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => null,
            ]
        ]);

        DB::table('currencies')->insert([
            [
                "id" => 1,
                "amount_lak" => 3149.30,
                "amount_cny" => 1,
                "active" => true,
                "created_by" => null,
            ],
            [
                "id" => 2,
                "amount_lak" => 3148.46,
                "amount_cny" => 1,
                "active" => true,
                "created_by" => null,
            ]
        ]);
    }
}
