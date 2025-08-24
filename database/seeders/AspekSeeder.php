<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AspekSeeder extends Seeder
{
    public function run(): void
    {
        $aspekNames = [
            'Kerja Sama Team',
            'Komunikasi',
            'Tanggung Jawab',
            'Kedisiplinan',
            'Kontribusi Terhadap Target Team',
        ];

        $start = Carbon::create(2024, 1, 1); // Januari 2024
        $end   = Carbon::create(2025, 8, 1); // Agustus 2025 (inklusif)

        $now = now();
        $rows = [];

        for ($cur = $start->copy(); $cur->lessThanOrEqualTo($end); $cur->addMonthNoOverflow()) {
            $bulan = (int) $cur->format('n');   // 1..12
            $tahun = (int) $cur->format('Y');   // 2024/2025

            foreach ($aspekNames as $nama) {
                $rows[] = [
                    'nama'       => $nama,
                    'bulan'      => $bulan,
                    'tahun'      => $tahun,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Upsert agar aman dijalankan berulang (menghormati unique ['nama','bulan','tahun'])
        DB::table('aspeks')->upsert(
            $rows,
            ['nama', 'bulan', 'tahun'],
            ['updated_at']
        );
    }
}
