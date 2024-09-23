<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('departments')->insert([
            "id" => 1,
            "name" => "department",
            "description" => "",
            "active" => true,
        ]);

        DB::table('roles')->insert([
            "id" => 1,
            "name" => "roles",
            "description" => "",
            "active" => true,
        ]);

        DB::table('users')->insert([
            "id" => 1,
            "first_name" => "admin",
            "email" => "admin@example.com",
            "department_id" => 1,
            "role_id" => 1,
            "password" => bcrypt("password")
        ]);
    }
}
