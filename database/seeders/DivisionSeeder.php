<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Technical Support Team',
            'Chat Sales Agent',
            'Creatif Desain',
        ] as $name) {
            Division::updateOrCreate(['name' => $name], []);
        }
    }
}
