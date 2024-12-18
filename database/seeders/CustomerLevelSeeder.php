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
        /////////////////////////////////////

        DB::table('customer_levels')->insert([
            [
                "id" => 1,
                "name" => "level 1",
                "description" => "defult",
                "rate" => 100.00,
                "active" => true,
                "created_by" => 1,

            ],
            [
                "id" => 2,
                "name" => "level 2",
                "description" => "defult",
                "rate" => 98.00,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 3,
                "name" => "level 3",
                "description" => "defult",
                "rate" => 96.00,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 4,
                "name" => "level 4",
                "description" => "defult",
                "rate" => 94.00,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 5,
                "name" => "level 5",
                "description" => "defult",
                "rate" => 92.00,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 6,
                "name" => "level 6",
                "description" => "defult",
                "rate" => 90.00,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 7,
                "name" => "level 7",
                "description" => "defult",
                "rate" => 88.00,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 8,
                "name" => "level 8",
                "description" => "defult",
                "rate" => 86.00,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 9,
                "name" => "level 9",
                "description" => "defult",
                "rate" => 84.00,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 10,
                "name" => "level 10",
                "description" => "defult",
                "rate" => 82.00,
                "active" => true,
                "created_by" => 1,
            ]

        ]);

        DB::table('customers')->insert([
            [
                "id" => 1,
                "name" => "cutomer no.1",
                "phone" => "0111111111",
                "address" => "1/10",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 2,
                "name" => "cutomer no.2",
                "phone" => "0222222222",
                "address" => "2/20",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 3,
                "name" => "cutomer no.3",
                "phone" => "0333333333",
                "address" => "3/30",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 4,
                "name" => "cutomer no.4",
                "phone" => "0444444444",
                "address" => "4/40",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 5,
                "name" => "cutomer no.5",
                "phone" => "0555555555",
                "address" => "5/50",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 6,
                "name" => "cutomer no.6",
                "phone" => "0666666666",
                "address" => "6/60",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 7,
                "name" => "cutomer no.7",
                "phone" => "0777777777",
                "address" => "7/70",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 8,
                "name" => "cutomer no.8",
                "phone" => "0888888888",
                "address" => "8/80",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 9,
                "name" => "cutomer no.9",
                "phone" => "0999999999",
                "address" => "9/90",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 10,
                "name" => "cutomer no.10",
                "phone" => "0101010101",
                "address" => "10/100",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 11,
                "name" => "cutomer no.11",
                "phone" => "0110110110",
                "address" => "11/110",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 12,
                "name" => "cutomer no.12",
                "phone" => "0120120120",
                "address" => "12/120",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 13,
                "name" => "cutomer no.13",
                "phone" => "0130130130",
                "address" => "13/130",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ],
            [
                "id" => 14,
                "name" => "cutomer no.14",
                "phone" => "0140140140",
                "address" => "14/140",
                "customer_level_id" => 1,
                "active" => true,
                "verify" => true,
                "created_by" => 1,
            ]
        ]);

        DB::table('currencies')->insert([
            [
                "id" => 1,
                "amount_lak" => 3149.30,
                "amount_cny" => 1,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 2,
                "amount_lak" => 3148.46,
                "amount_cny" => 1,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 3,
                "amount_lak" => 3148.36,
                "amount_cny" => 1,
                "active" => true,
                "created_by" => 1,
            ],
            [
                "id" => 4,
                "amount_lak" => 3147.16,
                "amount_cny" => 1,
                "active" => true,
                "created_by" => 1,
            ]
        ]);
    }
}
