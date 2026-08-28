<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin\Admin;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::create([
            'first_name'    =>  'System',
            'last_name'     =>  'Administrator',
            'email'         =>  'admin@nwssu.edu.ph',
            'password'      =>  'Admin123',
        ]);
    }
}
