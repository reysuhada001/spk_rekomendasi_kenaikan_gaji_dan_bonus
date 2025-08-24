<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class KpiDivisiSeeder extends Seeder
{
    public function run(): void
    {
        // --- Definisi KPI per divisi (tipe lowercase mengikuti enum di migration) ---
        $definition = [
            'technical support team' => [
                ['nama' => 'Jumlah Kendala Yang Diselesaikan',                       'tipe' => 'kuantitatif', 'satuan' => 'Kendala', 'target' => 50],
                ['nama' => 'Tingkat Kepuasan Pengguna Terhadap Pelayanan',           'tipe' => 'kualitatif',  'satuan' => 'Point',   'target' => 85],
                ['nama' => 'Rata-Rata Waktu Respon Pelayanan',                       'tipe' => 'response',    'satuan' => 'Menit',   'target' => 15],
                ['nama' => 'Persentase Penyelesaian Kendala sesuai SLA',             'tipe' => 'persentase',  'satuan' => '%',       'target' => 95],
            ],
            'chat sales agent' => [
                ['nama' => 'Jumlah Lead Yang Ditangani',                             'tipe' => 'kuantitatif', 'satuan' => 'Lead',    'target' => 100],
                ['nama' => 'Kualitas Interaksi Chat dengan Pelanggan',               'tipe' => 'kualitatif',  'satuan' => 'Point',   'target' => 85],
                ['nama' => 'Rata-Rata Waktu Respon Chat Masuk',                      'tipe' => 'response',    'satuan' => 'Detik',   'target' => 60],
                ['nama' => 'Rata-Rata Konversi Leads Menjadi Transaksi',             'tipe' => 'persentase',  'satuan' => '%',       'target' => 30],
            ],
            'creatif desain' => [
                ['nama' => 'Jumlah Proyek Desain yang Diselesaikan',                 'tipe' => 'kuantitatif', 'satuan' => 'Desain',  'target' => 15],
                ['nama' => 'Kualitas Hasil Desain',                                  'tipe' => 'kualitatif',  'satuan' => 'Point',   'target' => 85],
                ['nama' => 'Rata-Rata Waktu Penyelesaian Permintaan Desain',         'tipe' => 'response',    'satuan' => 'Hari',    'target' => 2],
                ['nama' => 'Persentase Proyek Tepat Waktu',                          'tipe' => 'persentase',  'satuan' => '%',       'target' => 95],
            ],
        ];

        // --- Pastikan divisi ada, jika belum ada maka dibuat ---
        $divisionIds = [];
        foreach (array_keys($definition) as $divNameRaw) {
            $divName = Str::title($divNameRaw); // contoh: "technical support team" -> "Technical Support Team"
            $existing = DB::table('divisions')->whereRaw('LOWER(name) = ?', [strtolower($divName)])->first();

            if (!$existing) {
                $divisionIds[$divNameRaw] = DB::table('divisions')->insertGetId([
                    'name'       => $divName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $divisionIds[$divNameRaw] = $existing->id;
            }
        }

        // --- Range bulan: Jan 2024 s/d Jul 2025 ---
        $start = Carbon::create(2024, 1, 1);
        $end   = Carbon::create(2025, 8, 1);

        $rows = [];
        for ($cursor = $start->copy(); $cursor <= $end; $cursor->addMonth()) {
            foreach ($definition as $divKey => $kpis) {
                foreach ($kpis as $kpi) {
                    $rows[] = [
                        'division_id' => $divisionIds[$divKey],
                        'nama'        => $kpi['nama'],
                        'tipe'        => $kpi['tipe'],
                        'satuan'      => $kpi['satuan'],
                        'target'      => $kpi['target'],
                        'bobot'       => null,                 // (opsional) akan diisi oleh AHP nanti
                        'bulan'       => (int) $cursor->month,
                        'tahun'       => (int) $cursor->year,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }
            }
        }

        // Insert (228 baris: 3 divisi × 4 KPI × 19 bulan)
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('kpi_divisi')->insert($chunk);
        }
    }
}
