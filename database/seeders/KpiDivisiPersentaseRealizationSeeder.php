<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Division;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiPersentaseRealization;

class KpiDivisiPersentaseRealizationSeeder extends Seeder
{
    public function run(): void
    {
        // -- Pastikan divisi ada
        $tstDiv = Division::where('name','like','%Technical Support Team%')->first();
        $csaDiv = Division::where('name','like','%Chat Sales Agent%')->first();
        $cdDiv  = Division::where('name','like','%Creatif Desain%')->first();
        if (!$tstDiv || !$csaDiv || !$cdDiv) {
            throw new \RuntimeException('Seed Division dulu (TST, CSA, Creatif Desain).');
        }

        // Actor opsional utk created_by/updated_by jika kolom tersedia
        $actorId = optional(User::whereIn('role',['hr','owner'])->orderBy('id')->first())->id;

        // Jan 2024 s/d Aug 2025
        $months = $this->monthRange(2024,1, 2025,8);

        DB::transaction(function() use ($months,$tstDiv,$csaDiv,$cdDiv,$actorId) {
            foreach ($months as $idx => [$bulan,$tahun]) {

                // -- KPI persentase per divisi/periode (1 KPI per divisi per bulan)
                $kpiTst = KpiDivisi::firstOrCreate(
                    [
                        'division_id'=>$tstDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,
                        'tipe'=>'persentase','nama'=>'Persentase Penyelesaian Kendala sesuai SLA'
                    ],
                    ['satuan'=>'%','target'=>95,'bobot'=>0.1875]
                );
                $kpiCsa = KpiDivisi::firstOrCreate(
                    [
                        'division_id'=>$csaDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,
                        'tipe'=>'persentase','nama'=>'Rata-Rata Konversi Leads Menjadi Transaksi'
                    ],
                    ['satuan'=>'%','target'=>30,'bobot'=>0.25]
                );
                $kpiCd = KpiDivisi::firstOrCreate(
                    [
                        'division_id'=>$cdDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,
                        'tipe'=>'persentase','nama'=>'Persentase Proyek Tepat Waktu'
                    ],
                    ['satuan'=>'%','target'=>95,'bobot'=>0.125]
                );

                // -- Pola siklik (GT/EQ/LT) bulanan
                $role = ['GT','EQ','LT'][$idx % 3];

                $this->seedOne($bulan,$tahun,$tstDiv,$kpiTst,$role,$actorId);
                $this->seedOne($bulan,$tahun,$csaDiv,$kpiCsa,$role,$actorId);
                $this->seedOne($bulan,$tahun,$cdDiv ,$kpiCd ,$role,$actorId);
            }
        });
    }

    private function seedOne(
        int $bulan,
        int $tahun,
        Division $div,
        KpiDivisi $kpi,
        string $role,
        ?int $actorId = null
    ): void {
        $target = (float)$kpi->target;
        $real   = $this->realPercentByRole($target, $role);
        $score  = $this->scorePercentage($real, $target); // skor raw (0..200)

        // Siapkan payload; created_by/updated_by opsional
        $payload = [
            'division_id' => $div->id,
            'bulan'       => $bulan,
            'tahun'       => $tahun,
            'target'      => $target,
            'realization' => $real,
            'score'       => $score,      // raw; weighted dipakai saat komposisi
            'status'      => 'approved',
            'hr_note'     => null,
        ];
        if (Schema::hasColumn('kpi_divisi_persentase_realizations','created_by') && $actorId) {
            $payload['created_by'] = $actorId;
        }
        if (Schema::hasColumn('kpi_divisi_persentase_realizations','updated_by') && $actorId) {
            $payload['updated_by'] = $actorId;
        }

        KpiDivisiPersentaseRealization::updateOrCreate(
            ['kpi_divisi_id' => $kpi->id],
            $payload
        );
    }

    private function realPercentByRole(float $target, string $role): float
    {
        $role = strtoupper($role);
        return match ($role) {
            // Sedikit di atas/bawah target agar realistis
            'GT'    => round($target + max(1.0, $target*0.02), 2),
            'LT'    => round(max(0.0, $target - max(3.0, $target*0.05)), 2),
            default => round($target, 2),
        };
    }

    /* ===== Skor persentase — identik controller (anchor 60/85/98, cap 200) ===== */
    private function scorePercentage(float $real, float $target): float
    {
        $eps = 1e-9;

        if ($target <= 0) {
            // Konsisten: jika tidak ada target, real<=0 -> 100; bila real>0 -> 150 (dibatasi)
            return ($real <= 0) ? 100.0 : 150.0;
        }

        if ($real >= $target) {
            $score = 100.0 * ($real / max($target, $eps));
            return round(min($score, 200.0), 2);  // CAP 200
        }

        // real < target -> fuzzy naik (60–85–98)
        $x = max(0.0, min(1.0, $real / max($target,$eps)));

        $muLow=0.0; $muMed=0.0; $muHigh=0.0;
        // L : 0 .. 0.3 .. 0.6
        if      ($x <= 0.3) $muLow = 1.0;
        elseif  ($x <= 0.6) $muLow = (0.6 - $x) / (0.6 - 0.3 + $eps);

        // M : 0.4 .. 0.6 .. 0.8
        if      ($x > 0.4 && $x <= 0.6) $muMed = ($x - 0.4) / (0.6 - 0.4 + $eps);
        elseif  ($x > 0.6 && $x <= 0.8) $muMed = (0.8 - $x) / (0.8 - 0.6 + $eps);

        // H : 0.7 .. 1.0 .. 1.0
        if      ($x > 0.7 && $x <= 1.0) $muHigh = ($x - 0.7) / (1.0 - 0.7 + $eps);

        $den = $muLow + $muMed + $muHigh;
        if ($den <= 0) return 60.0;

        $score = ($muLow*60 + $muMed*85 + $muHigh*98) / $den;
        return round($score, 2);
    }

    /* ===== Util ===== */

    private function monthRange(int $y1,int $m1,int $y2,int $m2): array
    {
        $out=[]; $y=$y1; $m=$m1;
        while ($y<$y2 || ($y===$y2 && $m<=$m2)) {
            $out[]=[$m,$y];
            $m++; if($m>12){$m=1;$y++;}
        }
        return $out;
    }
}
