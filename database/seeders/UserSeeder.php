<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'full_name'      => 'HR Admin',
            'nik'            => 'HR001',
            'photo'          => null,
            'email'          => 'hr@example.com',
            'username'       => 'hradmin',
            'password'       => Hash::make('password123'),
            'plain_password' => 'password123',
            'role'           => 'hr',
            'division_id'    => null,
        ]);
    }
}
