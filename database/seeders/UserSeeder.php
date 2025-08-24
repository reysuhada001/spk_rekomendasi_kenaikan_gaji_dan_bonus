<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Division;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan division tersedia (aman kalau sudah ada)
        $divTS  = Division::firstOrCreate(['name' => 'Technical Support Team']);
        $divCSA = Division::firstOrCreate(['name' => 'Chat Sales Agent']);
        $divCD  = Division::firstOrCreate(['name' => 'Creatif Desain']);

        // Helper upsert by username
        $make = function(array $data) {
            return User::updateOrCreate(
                ['username' => $data['username']],
                [
                    'full_name'      => $data['full_name'],
                    'nik'            => $data['nik'],
                    'photo'          => null,
                    'email'          => $data['email'],
                    'password'       => Hash::make('password'),
                    'plain_password' => 'password',
                    'role'           => $data['role'],
                    'division_id'    => $data['division_id'],
                ]
            );
        };

        // HR
        $make([
            'full_name'   => 'HR Admin',
            'nik'         => 'HR001',
            'email'       => 'hr@example.com',
            'username'    => 'hradmin',
            'role'        => 'hr',
            'division_id' => null,
        ]);

        // Owner (tanpa divisi)
        $make([
            'full_name'   => 'Owner',
            'nik'         => 'OWN001',
            'email'       => 'owner@example.com',
            'username'    => 'owner',
            'role'        => 'owner',
            'division_id' => null,
        ]);

        // ===== Leaders =====
        // Leader — Technical Support Team
        $make([
            'full_name'   => 'Saepul Akbar',
            'nik'         => 'TSL001',
            'email'       => 'saepul.akbar@example.com',
            'username'    => 'saepul',
            'role'        => 'leader',
            'division_id' => $divTS->id,
        ]);

        // Leader — Chat Sales Agent
        $make([
            'full_name'   => 'Diar Putri Yani',
            'nik'         => 'CSL001',
            'email'       => 'diar.putri@example.com',
            'username'    => 'diar',
            'role'        => 'leader',
            'division_id' => $divCSA->id,
        ]);

        // Leader — Creatif Desain
        $make([
            'full_name'   => 'Cahyono Rajab',
            'nik'         => 'CDL001',
            'email'       => 'cahyono.rajab@example.com',
            'username'    => 'cahyono',
            'role'        => 'leader',
            'division_id' => $divCD->id,
        ]);

        // ===== Karyawan =====
        // Karyawan — Technical Support Team
        $make([
            'full_name'   => 'Moh. Handika Nurfadli',
            'nik'         => 'TS001',
            'email'       => 'handika@example.com',
            'username'    => 'handika',
            'role'        => 'karyawan',
            'division_id' => $divTS->id,
        ]);

        $make([
            'full_name'   => 'Devan Aditya Halimawan',
            'nik'         => 'TS002',
            'email'       => 'devan@example.com',
            'username'    => 'devan',
            'role'        => 'karyawan',
            'division_id' => $divTS->id,
        ]);

        $make([
            'full_name'   => 'Muh. Rizal Fauzi',
            'nik'         => 'TS003',
            'email'       => 'rizal@example.com',
            'username'    => 'rizal',
            'role'        => 'karyawan',
            'division_id' => $divTS->id,
        ]);

        // Karyawan — Chat Sales Agent
        $make([
            'full_name'   => 'Ariyani',
            'nik'         => 'CSA001',
            'email'       => 'ariyani@example.com',
            'username'    => 'ariyani',
            'role'        => 'karyawan',
            'division_id' => $divCSA->id,
        ]);

        // Karyawan — Creatif Desain
        $make([
            'full_name'   => 'Muh. Akmal AL Fazar',
            'nik'         => 'CD001',
            'email'       => 'akmal@example.com',
            'username'    => 'akmal',
            'role'        => 'karyawan',
            'division_id' => $divCD->id,
        ]);

        $make([
            'full_name'   => 'Ratna Damayanti',
            'nik'         => 'CD002',
            'email'       => 'ratna@example.com',
            'username'    => 'ratna',
            'role'        => 'karyawan',
            'division_id' => $divCD->id,
        ]);

        $make([
            'full_name'   => 'Laela Nurul Fadhilah',
            'nik'         => 'CD003',
            'email'       => 'laela@example.com',
            'username'    => 'laela',
            'role'        => 'karyawan',
            'division_id' => $divCD->id,
        ]);
    }
}
