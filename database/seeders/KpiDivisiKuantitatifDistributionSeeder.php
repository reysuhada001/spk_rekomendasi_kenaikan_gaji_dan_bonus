<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\Division;
use App\Models\User;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiDistribution;
use App\Models\KpiDivisiDistributionItem;

class KpiDivisiKuantitatifDistributionSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil divisi yang dipakai
        $divisions = Division::whereIn('name', [
            'Technical Support Team',
            'Chat Sales Agent',
            'Creatif Desain',
        ])->get()->keyBy('id');

        if ($divisions->isEmpty()) return;

        // Creator (leader divisi → fallback HR → fallback user pertama)
        $leaderByDivision = User::where('role', 'leader')->pluck('id', 'division_id');
        $hrId = User::where('role', 'hr')->value('id') ?? User::query()->value('id');

        // Kolom aktual (agar aman di berbagai skema)
        $distCols = Schema::getColumnListing('kpi_divisi_distributions');
        $itemCols = Schema::getColumnListing('kpi_divisi_distribution_items');

        $has_created_by_h = in_array('created_by', $distCols, true);
        $has_updated_by_h = in_array('updated_by', $distCols, true);
        $has_created_by_i = in_array('created_by', $itemCols, true);
        $has_updated_by_i = in_array('updated_by', $itemCols, true);

        $onlyCols = function(array $payload, array $allowedCols): array {
            return array_intersect_key($payload, array_flip($allowedCols));
        };

        // Periode Jan 2024 s/d Agu 2025
        $start = new \DateTimeImmutable('2024-01-01');
        $end   = new \DateTimeImmutable('2025-08-01');

        for ($dt = $start; $dt <= $end; $dt = $dt->modify('+1 month')) {
            $bulan = (int)$dt->format('n');
            $tahun = (int)$dt->format('Y');

            foreach ($divisions as $div) {
                $creatorId = $leaderByDivision[$div->id] ?? $hrId;

                // KPI kuantitatif untuk divisi & periode ini
                $kpis = KpiDivisi::where([
                        'division_id' => $div->id,
                        'bulan'       => $bulan,
                        'tahun'       => $tahun,
                        'tipe'        => 'kuantitatif',
                    ])->get();

                if ($kpis->isEmpty()) continue;

                // Header distribusi
                $headerPayload = [
                    'division_id' => $div->id,
                    'bulan'       => $bulan,
                    'tahun'       => $tahun,
                    'status'      => 'approved',
                    'hr_note'     => null,
                ];
                if ($has_created_by_h) $headerPayload['created_by'] = $creatorId;
                if ($has_updated_by_h) $headerPayload['updated_by'] = $creatorId;

                $dist = KpiDivisiDistribution::updateOrCreate(
                    [
                        'division_id' => $div->id,
                        'bulan'       => $bulan,
                        'tahun'       => $tahun,
                    ],
                    $onlyCols($headerPayload, $distCols)
                );

                // Karyawan divisi (urut nama untuk tie-break yang stabil)
                $users = User::where('division_id', $div->id)
                    ->where('role', 'karyawan')
                    ->orderBy('full_name')
                    ->get();

                if ($users->isEmpty()) continue;

                foreach ($kpis as $kpi) {
                    // Target total dibulatkan ke integer lalu dibagi dengan **largest remainder**
                    $T = (int) round((float)($kpi->target ?? 0));
                    $n = max(1, $users->count());

                    $base = intdiv($T, $n);
                    $rem  = $T - ($base * $n);

                    // Semua dapat base; +1 ke R orang pertama (sesuai urutan nama)
                    foreach ($users as $idx => $u) {
                        $perUserInt = $base + ($idx < $rem ? 1 : 0);

                        $itemPayload = [
                            'distribution_id' => $dist->id,
                            'kpi_divisi_id'   => $kpi->id,
                            'user_id'         => $u->id,
                            'target'          => $perUserInt, // PASTI bilangan bulat
                        ];
                        if ($has_created_by_i) $itemPayload['created_by'] = $creatorId;
                        if ($has_updated_by_i) $itemPayload['updated_by'] = $creatorId;

                        KpiDivisiDistributionItem::updateOrCreate(
                            [
                                'distribution_id' => $dist->id,
                                'kpi_divisi_id'   => $kpi->id,
                                'user_id'         => $u->id,
                            ],
                            $onlyCols($itemPayload, $itemCols)
                        );
                    }
                }
            }
        }
    }
}
