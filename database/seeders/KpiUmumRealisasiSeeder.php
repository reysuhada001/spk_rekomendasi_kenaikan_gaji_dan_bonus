<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\KpiUmum;
use App\Models\KpiUmumRealization;
use App\Models\KpiUmumRealizationItem;

class KpiUmumRealisasiSeeder extends Seeder
{
    // Cap skor maksimum untuk KPI Umum (sinkron dgn controller)
    private const CAP_MAX = 150.0;

    public function run(): void
    {
        // ====== KONFIG KPI UMUM (target & bobot per KPI) ======
        // Bobot (desimal) sesuai yang kamu tetapkan: JT .533333, KL .266667, KH .133333, RS .066667
        $kpiDefs = [
            'JT' => ['nama' => 'Jumlah Tugas Diselesaikan Tepat Waktu', 'tipe' => 'kuantitatif', 'satuan' => 'Tugas', 'target' => 40, 'bobot' => 0.533333],
            'KL' => ['nama' => 'Kualitas Hasil Pekerjaan',               'tipe' => 'kualitatif', 'satuan' => 'Point', 'target' => 90, 'bobot' => 0.266667],
            'RS' => ['nama' => 'Rata-Rata Waktu Respon Terhadap Permintaan', 'tipe' => 'response', 'satuan' => 'Menit', 'target' => 60, 'bobot' => 0.066667],
            'KH' => ['nama' => 'Persentase Kehadiran',                   'tipe' => 'persentase', 'satuan' => '%',     'target' => 95, 'bobot' => 0.133333],
        ];

        // ====== AMBIL USER BERDASARKAN NAMA & DIVISI ======
        $find = fn(string $name) => User::where('full_name', $name)->first();

        // Technical Support Team
        $handika = $find('Moh. Handika Nurfadli');
        $devan   = $find('Devan Aditya Halimawan');
        $rizal   = $find('Muh. Rizal Fauzi');

        // Chat Sales Agent
        $ariyani = $find('Ariyani');

        // Creatif Desain
        $akmal = $find('Muh. Akmal AL Fazar'); // pastikan penulisan nama sama dengan data di DB (kolasi MySQL biasanya case-insensitive)
        $ratna = $find('Ratna Damayanti');
        $laela = $find('Laela Nurul Fadhilah');

        $tst  = array_values(array_filter([$handika, $devan, $rizal]));
        $cdes = array_values(array_filter([$akmal, $ratna, $laela]));
        $csa  = array_values(array_filter([$ariyani]));

        if (count($tst) !== 3 || count($cdes) !== 3 || count($csa) !== 1) {
            $missing = [];
            if (count($tst)  !== 3) $missing[] = 'Technical Support Team (3 orang)';
            if (count($cdes) !== 3) $missing[] = 'Creatif Desain (3 orang)';
            if (count($csa)  !== 1) $missing[] = 'Chat Sales Agent (1 orang)';
            throw new \RuntimeException('Seeder KPI Umum: user belum lengkap: '.implode(', ', $missing));
        }

        // ====== RENTANG BULAN: Jan 2024 s/d Aug 2025 (inklusif) ======
        $months = $this->monthRange(['y'=>2024,'m'=>1], ['y'=>2025,'m'=>8]); // array of [bulan, tahun], index 0..19

        DB::transaction(function() use ($months, $kpiDefs, $tst, $cdes, $csa) {
            foreach ($months as $idx => [$bulan, $tahun]) {

                // Pastikan 4 KPI-Umum untuk periode ini tersedia (sekali per periode)
                $kpiByCode = [];
                foreach ($kpiDefs as $code => $def) {
                    $kpi = KpiUmum::firstOrCreate(
                        ['bulan' => $bulan, 'tahun' => $tahun, 'nama' => $def['nama']], // unik per periode + nama
                        [
                            'tipe'   => $def['tipe'],
                            'satuan' => $def['satuan'],
                            'target' => $def['target'],
                            'bobot'  => $def['bobot'],
                        ]
                    );
                    $kpiByCode[$code] = $kpi;
                }

                // ====== OVERRIDE KHUSUS: Agustus 2025 pakai realisasi manual ======
                if ($bulan === 8 && $tahun === 2025) {
                    // TST
                    $this->upsertUserKpiUmumManual($tst[0], $bulan, $tahun, $kpiByCode, ['JT'=>50,'KL'=>95,'KH'=>97,'RS'=>55]); // Moh. Handika Nurfadli
                    $this->upsertUserKpiUmumManual($tst[1], $bulan, $tahun, $kpiByCode, ['JT'=>40,'KL'=>90,'KH'=>95,'RS'=>60]); // Devan Aditya Halimawan
                    $this->upsertUserKpiUmumManual($tst[2], $bulan, $tahun, $kpiByCode, ['JT'=>36,'KL'=>88,'KH'=>92,'RS'=>65]); // Muh. Rizal Fauzi

                    // CSA (single)
                    $this->upsertUserKpiUmumManual($csa[0], $bulan, $tahun, $kpiByCode, ['JT'=>41,'KL'=>94,'KH'=>96,'RS'=>58]); // Ariyani

                    // Creatif Desain
                    $this->upsertUserKpiUmumManual($cdes[0], $bulan, $tahun, $kpiByCode, ['JT'=>40,'KL'=>90,'KH'=>95,'RS'=>60]); // Muh. Akmal Al Fazar
                    $this->upsertUserKpiUmumManual($cdes[1], $bulan, $tahun, $kpiByCode, ['JT'=>36,'KL'=>88,'KH'=>92,'RS'=>65]); // Ratna Damayanti
                    $this->upsertUserKpiUmumManual($cdes[2], $bulan, $tahun, $kpiByCode, ['JT'=>50,'KL'=>95,'KH'=>96,'RS'=>55]); // Laela Nurul Fadhilah

                    // lanjut ke bulan berikutnya (jangan jalankan pola rotasi untuk bulan ini)
                    continue;
                }

                // ====== Pola rotasi per divisi (untuk bulan selain Agustus 2025) ======
                // TST & Creatif Desain: tiap bulan rotasi (>), (=), (<) antar 3 orang
                $statuses3 = $this->rotatingTriple($idx); // contoh bulan 0: ['>','=','<'], bulan 1: ['<','>','='], bulan 2: ['=','<','>'], dst.

                // Chat Sales Agent: pola mix >,=,<,>,=,<, ...
                $status1  = $this->cycleSingle($idx);     // '>', '=', '<' berulang

                // ====== Isi untuk Technical Support Team (3 orang) ======
                foreach ([[$tst[0], $statuses3[0]], [$tst[1], $statuses3[1]], [$tst[2], $statuses3[2]]] as [$user, $flag]) {
                    $this->upsertUserKpiUmum($user, $bulan, $tahun, $kpiByCode, $flag);
                }

                // ====== Isi untuk Creatif Desain (3 orang) ======
                foreach ([[$cdes[0], $statuses3[0]], [$cdes[1], $statuses3[1]], [$cdes[2], $statuses3[2]]] as [$user, $flag]) {
                    $this->upsertUserKpiUmum($user, $bulan, $tahun, $kpiByCode, $flag);
                }

                // ====== Isi untuk Chat Sales Agent (1 orang) ======
                $this->upsertUserKpiUmum($csa[0], $bulan, $tahun, $kpiByCode, $status1);
            }
        });
    }

    /** Buat/Update header + item Realisasi KPI Umum utk 1 user 1 periode, status aggregate: '>' '=' '<' */
    private function upsertUserKpiUmum(User $user, int $bulan, int $tahun, array $kpiByCode, string $status): void
    {
        // Tentukan realisasi per KPI berdasarkan status aggregate
        // > : JT 48, KL 108, RS 48 (lebih cepat), KH 100
        // = : JT 40, KL 90,  RS 60, KH 95
        // < : JT 32, KL 72,  RS 72 (lebih lambat), KH 85
        $real = match ($status) {
            '>' => ['JT'=>48, 'KL'=>108, 'RS'=>48, 'KH'=>100],
            '=' => ['JT'=>40, 'KL'=>90,  'RS'=>60, 'KH'=>95 ],
            '<' => ['JT'=>32, 'KL'=>72,  'RS'=>72, 'KH'=>85 ],
            default => ['JT'=>40, 'KL'=>90, 'RS'=>60, 'KH'=>95],
        };

        $this->upsertUserKpiUmumManual($user, $bulan, $tahun, $kpiByCode, [
            'JT' => $real['JT'],
            'KL' => $real['KL'],
            'KH' => $real['KH'],
            'RS' => $real['RS'],
        ]);
    }

    /** Versi manual: realisasi per KPI ditentukan eksplisit (keys: JT, KL, KH, RS) */
    private function upsertUserKpiUmumManual(User $user, int $bulan, int $tahun, array $kpiByCode, array $real): void
    {
        // Hitung skor per item sesuai controller (linear + fuzzy & cap 150)
        $items = [];
        $sumW  = 0.0;
        $sumWS = 0.0;

        foreach ($kpiByCode as $code => $kpi) {
            $tipe      = $kpi->tipe;
            $target    = (float)$kpi->target;
            $w         = (float)$kpi->bobot;
            $realisasi = (float)$real[$code]; // code salah satu dari JT/KL/KH/RS

            $score = $this->scorePerKpi($tipe, $target, $realisasi);   // 0..150 (>=target) / fuzzy <target

            $items[] = [
                'kpi'       => $kpi,
                'tipe'      => $tipe,
                'satuan'    => $kpi->satuan,
                'target'    => $target,
                'realisasi' => $realisasi,
                'score'     => $score,
                'w'         => $w,
            ];

            $sumW  += $w;
            $sumWS += $w * $score;
        }

        $final = $sumW > 0 ? ($sumWS / $sumW) : (array_sum(array_column($items,'score')) / max(1,count($items)));
        $final = round($final, 2);

        // Upsert header
        $realHdr = KpiUmumRealization::updateOrCreate(
            ['user_id' => $user->id, 'bulan' => $bulan, 'tahun' => $tahun],
            [
                'division_id' => $user->division_id,
                'status'      => 'approved',  // langsung approved supaya terbaca ke rekomendasi bonus
                'hr_note'     => null,
                'total_score' => $final,
            ]
        );

        // Upsert items
        foreach ($items as $it) {
            KpiUmumRealizationItem::updateOrCreate(
                ['realization_id' => $realHdr->id, 'kpi_umum_id' => $it['kpi']->id],
                [
                    'tipe'      => $it['tipe'],
                    'satuan'    => $it['satuan'],
                    'target'    => $it['target'],
                    'realisasi' => $it['realisasi'],
                    'score'     => $it['score'],
                ]
            );
        }
    }

    /** Skor per KPI (sinkron dgn controller KpiUmumRealizationController) */
    private function scorePerKpi(string $tipe, float $target, float $realisasi): float
    {
        if ($tipe !== 'response' && $target <= 0) return 0.0;

        if ($tipe === 'response') {
            if ($realisasi <= 0) return 0.0;
            $ratio = $target / $realisasi; // lebih kecil lebih baik
            if ($ratio >= 1.0) return min(self::CAP_MAX, 100.0 * $ratio);
            return $this->fuzzyBelowTarget($ratio);
        } else {
            $ratio = $realisasi / max($target, 1e-9); // lebih besar lebih baik
            if ($ratio >= 1.0) return min(self::CAP_MAX, 100.0 * $ratio);
            return $this->fuzzyBelowTarget($ratio);
        }
    }

    /** Fuzzy untuk rasio < 1 (anchor dinaikkan: Low=70, Near=95) */
    private function fuzzyBelowTarget(float $r): float
    {
        // Membership
        $muLow  = ($r <= 0.4) ? 1.0 : (($r >= 0.8) ? 0.0 : (0.8 - $r) / 0.4);
        $muNear = ($r <= 0.6) ? 0.0 : (($r >= 1.0) ? 1.0 : ($r - 0.6) / 0.4);

        $num = $muLow * 70 + $muNear * 95;
        $den = $muLow + $muNear;
        return $den > 0 ? ($num / $den) : 70.0;
    }

    /** Daftar bulan [ [m,y], ... ] dari (y1,m1) ke (y2,m2) inklusif */
    private function monthRange(array $start, array $end): array
    {
        $out = [];
        $y = $start['y']; $m = $start['m'];
        while ($y < $end['y'] || ($y === $end['y'] && $m <= $end['m'])) {
            $out[] = [$m, $y];
            $m++; if ($m > 12) { $m = 1; $y++; }
        }
        return $out;
    }

    /** Rotasi 3-peran per bulan: bulan0 ['>','=','<'], bulan1 ['<','>','='], bulan2 ['=','<','>'], … */
    private function rotatingTriple(int $monthIndex): array
    {
        $patterns = [
            ['>','=','<'],
            ['<','>','='],
            ['=','<','>'],
        ];
        return $patterns[$monthIndex % 3];
    }

    /** Siklus tunggal untuk 1 orang: '>', '=', '<', '>', '=', '<', ... */
    private function cycleSingle(int $monthIndex): string
    {
        return ['>','=','<'][$monthIndex % 3];
    }
}
