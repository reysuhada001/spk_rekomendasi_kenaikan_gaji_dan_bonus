<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Division;
use App\Models\KpiUmum;
use App\Models\KpiDivisi;

class AhpKpiWeightSeeder extends Seeder
{
    /** Random Index Saaty */
    private array $RI = [
        1=>0.00,2=>0.00,3=>0.58,4=>0.90,5=>1.12,6=>1.24,7=>1.32,8=>1.41,9=>1.45,10=>1.49,
    ];

    public function run(): void
    {
        $months = $this->monthRange(2024,1, 2025,8);

        // =========================
        // KPI UMUM (pakai alias agar bobot tidak bolong)
        // =========================
        // Struktur: code => [name, aliases[], tipe, satuan, target]
        $umumDefs = [
            'JT' => [
                'name'    => 'Jumlah Tugas Diselesaikan Tepat Waktu',
                'aliases' => ['Jumlah Tugas Diselesaikan Tepat Waktu'],
                'tipe'    => 'kuantitatif',
                'satuan'  => 'Tugas',
                'target'  => 40,
            ],
            'KL' => [
                'name'    => 'Kualitas Hasil Pekerjaan',
                'aliases' => ['Kualitas Hasil Pekerjaan'],
                'tipe'    => 'kualitatif',
                'satuan'  => 'Point',
                'target'  => 90,
            ],
            'KH' => [
                'name'    => 'Persentase Kehadiran',
                'aliases' => ['Persentase Kehadiran'],
                'tipe'    => 'persentase',
                'satuan'  => '%',
                'target'  => 95,
            ],
            'RS' => [
                'name'    => 'Rata-Rata Waktu Respon Terhadap Permintaan',
                'aliases' => ['Rata-Rata Waktu Respon Terhadap Permintaan'],
                'tipe'    => 'response',
                'satuan'  => 'Menit',
                'target'  => 60,
            ],
        ];
        // Urutan: JT_KL, JT_KH, JT_RS, KL_KH, KL_RS, KH_RS
        $umumPairs = [
            'JT_KL' => 2,
            'JT_KH' => 4,
            'JT_RS' => 8,
            'KL_KH' => 2,
            'KL_RS' => 4,
            'KH_RS' => 2,
        ];

        // =========================
        // KPI DIVISI (biarkan seperti kode kamu)
        // =========================

        // --- Technical Support Team (TST)
        $tstCrit = [
            'QTY' => 'Jumlah Kendala Yang Diselesaikan',                 // kuantitatif
            'QLT' => 'Tingkat Kepuasan Pengguna Terhadap Pelayanan',     // kualitatif
            'RSP' => 'Rata-Rata Waktu Respon Pelayanan',                 // response
            'PCT' => 'Persentase Penyelesaian Kendala sesuai SLA',       // persentase
        ];
        // Urutan: QTY_QLT, QTY_RSP, QTY_PCT, QLT_RSP, QLT_PCT, RSP_PCT
        $tstPairs = [
            'QTY_QLT' => 3,
            'QTY_RSP' => 3,
            'QTY_PCT' => 9,
            'QLT_RSP' => 1,
            'QLT_PCT' => 3,
            'RSP_PCT' => 3,
        ];

        // --- Chat Sales Agent (CSA)
        $csaCrit = [
            'QTY' => 'Jumlah Lead Yang Ditangani',
            'QLT' => 'Kualitas Interaksi Chat dengan Pelanggan',
            'RSP' => 'Rata-Rata Waktu Respon Chat Masuk',
            'PCT' => 'Rata-Rata Konversi Leads Menjadi Transaksi',
        ];
        $csaPairs = [
            'QTY_QLT' => 3,
            'QTY_RSP' => 9,
            'QTY_PCT' => 9,
            'QLT_RSP' => 3,
            'QLT_PCT' => 3,
            'RSP_PCT' => 1,
        ];

        // --- Creatif Desain (CD)
        $cdCrit = [
            'QTY' => 'Jumlah Proyek Desain yang Diselesaikan',
            'QLT' => 'Kualitas Hasil Desain',
            'RSP' => 'Rata-Rata Waktu Penyelesaian Permintaan Desain',
            'PCT' => 'Persentase Proyek Tepat Waktu',
        ];
        $cdPairs = [
            'QTY_QLT' => 2,
            'QTY_RSP' => 4,
            'QTY_PCT' => 4,
            'QLT_RSP' => 2,
            'QLT_PCT' => 2,
            'RSP_PCT' => 1,
        ];

        // Ambil id divisi
        $tstDiv = Division::where('name','like','%Technical Support Team%')->first();
        $csaDiv = Division::where('name','like','%Chat Sales Agent%')->first();
        $cdDiv  = Division::where('name','like','%Creatif Desain%')->first();

        DB::transaction(function() use(
            $months, $umumDefs, $umumPairs,
            $tstDiv,$csaDiv,$cdDiv,
            $tstCrit,$tstPairs,$csaCrit,$csaPairs,$cdCrit,$cdPairs
        ){
            foreach ($months as [$bulan,$tahun]) {
                // ---------- KPI UMUM (pakai alias, auto-create jika kosong) ----------
                $this->applyAhpForKpiUmum($bulan,$tahun,$umumDefs,$umumPairs);

                // ---------- KPI DIVISI (tetap) ----------
                if ($tstDiv) $this->applyAhpForKpiDivisi($tstDiv->id,$bulan,$tahun,$tstCrit,$tstPairs);
                if ($csaDiv) $this->applyAhpForKpiDivisi($csaDiv->id,$bulan,$tahun,$csaCrit,$csaPairs);
                if ($cdDiv)  $this->applyAhpForKpiDivisi($cdDiv->id,$bulan,$tahun,$cdCrit,$cdPairs);
            }
        });
    }

    /**
     * KPI Umum: update semua baris yang namanya masuk list alias.
     * Jika tidak ada satupun baris ditemukan, buat 1 baris dengan nama baku.
     */
    private function applyAhpForKpiUmum(int $bulan,int $tahun,array $defs,array $pairs): void
    {
        // Hitung bobot berdasarkan pairwise
        [$weights, $cr] = $this->computeAhp(array_keys($defs), $pairs);

        foreach ($defs as $code => $def) {
            $w = round($weights[$code] ?? 0.0, 6);
            $names = $def['aliases'] ?? [$def['name']];

            // Update semua alias yang ada
            $updated = KpiUmum::where('bulan',$bulan)
                ->where('tahun',$tahun)
                ->whereIn('nama', $names)
                ->update(['bobot' => $w]);

            // Jika tidak ada satupun, buat KPI dengan nama baku
            if ($updated === 0) {
                KpiUmum::firstOrCreate(
                    [
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'nama'  => $def['name'],
                    ],
                    [
                        'tipe'   => $def['tipe']   ?? 'kuantitatif',
                        'satuan' => $def['satuan'] ?? '',
                        'target' => $def['target'] ?? 0,
                        'bobot'  => $w,
                    ]
                );
            }
        }

        // (Opsional) kamu bisa log $cr untuk cek konsistensi: dump($bulan,$tahun,$cr);
    }

    private function applyAhpForKpiDivisi(int $divisionId,int $bulan,int $tahun,array $criteriaMap,array $pairs): void
    {
        $kpis = KpiDivisi::where('division_id',$divisionId)->where('bulan',$bulan)->where('tahun',$tahun)
            ->whereIn('nama', array_values($criteriaMap))
            ->get()->keyBy('nama');

        if ($kpis->count() < count($criteriaMap)) return;

        $ids = [];
        foreach ($criteriaMap as $code=>$name) $ids[$code] = (int)$kpis[$name]->id;

        [$weights, $cr] = $this->computeAhp(array_keys($ids), $pairs);

        foreach ($ids as $code=>$kpiId) {
            KpiDivisi::where('id',$kpiId)->update(['bobot' => round($weights[$code], 6)]);
        }
    }

    /**
     * @param array $codes  mis: ['JT','KL','KH','RS']
     * @param array $pairs  segitiga atas: ['JT_KL'=>2, 'JT_KH'=>4, ...]
     * @return array [weightsByCode, cr]
     */
    private function computeAhp(array $codes, array $pairs): array
    {
        $n = count($codes);

        // Matriks A NxN
        $A = array_fill(0,$n,array_fill(0,$n,1.0));
        for ($i=0;$i<$n;$i++){
            for ($j=$i+1;$j<$n;$j++){
                $key = $codes[$i].'_'.$codes[$j];
                $val = isset($pairs[$key]) ? (float)$pairs[$key] : 1.0;
                $val = max(1.0/9.0, min(9.0, $val)); // clamp ke 1/9..9
                $A[$i][$j] = $val;
                $A[$j][$i] = 1.0 / $val;
            }
        }

        // Normalisasi kolom → bobot
        $colSum = array_fill(0,$n,0.0);
        for ($j=0;$j<$n;$j++) for ($i=0;$i<$n;$i++) $colSum[$j] += $A[$i][$j];

        $N = array_fill(0,$n,array_fill(0,$n,0.0));
        for ($i=0;$i<$n;$i++)
            for ($j=0;$j<$n;$j++)
                $N[$i][$j] = $A[$i][$j] / max($colSum[$j], 1e-12);

        $w = array_fill(0,$n,0.0);
        for ($i=0;$i<$n;$i++) $w[$i] = array_sum($N[$i]) / $n;

        $wsum = array_sum($w);
        if ($wsum > 0) foreach ($w as $k=>$v) $w[$k] = $v / $wsum;

        // λmax, CI, CR (cek konsistensi)
        $Aw = array_fill(0,$n,0.0);
        for ($i=0;$i<$n;$i++) {
            $s=0.0; for ($j=0;$j<$n;$j++) $s += $A[$i][$j] * $w[$j];
            $Aw[$i] = $s;
        }
        $ratios = [];
        for ($i=0;$i<$n;$i++) $ratios[] = $w[$i] > 0 ? $Aw[$i] / $w[$i] : 0.0;
        $lambdaMax = array_sum($ratios) / $n;
        $ci = ($n>1) ? ($lambdaMax - $n)/($n-1) : 0.0;
        $ri = $this->RI[$n] ?? 1.49;
        $cr = $ri > 0 ? $ci/$ri : 0.0;

        $out = [];
        foreach ($codes as $i=>$code) $out[$code] = $w[$i];
        return [$out, $cr];
    }

    private function monthRange(int $y1,int $m1,int $y2,int $m2): array
    {
        $out=[]; $y=$y1; $m=$m1;
        while ($y<$y2 || ($y===$y2 && $m<=$m2)) { $out[]=[$m,$y]; $m++; if($m>12){$m=1;$y++;} }
        return $out;
    }
}
