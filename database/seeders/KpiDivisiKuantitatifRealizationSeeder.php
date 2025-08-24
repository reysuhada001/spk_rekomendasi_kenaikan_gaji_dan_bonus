<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Division;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiDistribution;
use App\Models\KpiDivisiDistributionItem;
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKuantitatifRealizationItem;

class KpiDivisiKuantitatifRealizationSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil divisi
        $tstDiv = Division::where('name','like','%Technical Support Team%')->first();
        $csaDiv = Division::where('name','like','%Chat Sales Agent%')->first();
        $cdDiv  = Division::where('name','like','%Creatif Desain%')->first();

        if (!$tstDiv || !$csaDiv || !$cdDiv) {
            throw new \RuntimeException('Pastikan divisi TST, Chat Sales Agent, dan Creatif Desain sudah dised.');
        }

        // Anggota (urut nama untuk rotasi konsisten & tie-break distribusi)
        $tstUsers = User::where('division_id',$tstDiv->id)->where('role','karyawan')->orderBy('full_name')->get()->values();
        $csaUsers = User::where('division_id',$csaDiv->id)->where('role','karyawan')->orderBy('full_name')->get()->values();
        $cdUsers  = User::where('division_id',$cdDiv->id)->where('role','karyawan')->orderBy('full_name')->get()->values();

        // Range bulan: Jan 2024 .. Aug 2025
        $months = $this->monthRange(2024,1, 2025,8);

        DB::transaction(function() use($months,$tstDiv,$csaDiv,$cdDiv,$tstUsers,$csaUsers,$cdUsers){
            foreach ($months as $idx => [$bulan,$tahun]) {

                // Pastikan KPI Kuantitatif per divisi/periode ada
                $kpiTst = KpiDivisi::firstOrCreate(
                    ['division_id'=>$tstDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,'tipe'=>'kuantitatif','nama'=>'Jumlah Kendala Yang Diselesaikan'],
                    ['satuan'=>'Kendala','target'=>50,'bobot'=>0.5625]
                );
                $kpiCsa = KpiDivisi::firstOrCreate(
                    ['division_id'=>$csaDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,'tipe'=>'kuantitatif','nama'=>'Jumlah Lead Yang Ditangani'],
                    ['satuan'=>'Lead','target'=>100,'bobot'=>0.25]
                );
                $kpiCd = KpiDivisi::firstOrCreate(
                    ['division_id'=>$cdDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,'tipe'=>'kuantitatif','nama'=>'Jumlah Proyek Desain yang Diselesaikan'],
                    ['satuan'=>'Desain','target'=>15,'bobot'=>0.50]
                );

                // ====== OVERRIDE KHUSUS: Agustus 2025 pakai data manual (Target & Realisasi per user) ======
                if ($bulan === 8 && $tahun === 2025) {
                    // TST
                    $handika = $tstUsers->firstWhere('full_name','Moh. Handika Nurfadli');
                    $devan   = $tstUsers->firstWhere('full_name','Devan Aditya Halimawan');
                    $rizal   = $tstUsers->firstWhere('full_name','Muh. Rizal Fauzi');

                    // CSA
                    $ariyani = $csaUsers->firstWhere('full_name','Ariyani');

                    // Creatif Desain (cek variasi ejaan AL/Al)
                    $akmal = $cdUsers->firstWhere('full_name','Muh. Akmal Al Fazar')
                           ?? $cdUsers->firstWhere('full_name','Muh. Akmal AL Fazar');
                    $ratna = $cdUsers->firstWhere('full_name','Ratna Damayanti');
                    $laela = $cdUsers->firstWhere('full_name','Laela Nurul Fadhilah');

                    foreach ([
                        // Technical Support Team
                        [$handika, $tstDiv, $kpiTst, 17, 19],
                        [$devan,   $tstDiv, $kpiTst, 17, 17],
                        [$rizal,   $tstDiv, $kpiTst, 16, 15],

                        // Chat Sales Agent
                        [$ariyani, $csaDiv, $kpiCsa, 100, 112],

                        // Creatif Desain
                        [$akmal,   $cdDiv,  $kpiCd,  5,   5],
                        [$ratna,   $cdDiv,  $kpiCd,  5,   4],
                        [$laela,   $cdDiv,  $kpiCd,  5,   6],
                    ] as $row) {
                        if (!$row[0]) {
                            throw new \RuntimeException('User tidak ditemukan untuk override Agustus 2025 (periksa ejaan nama).');
                        }
                        [$user,$div,$kpi,$target,$real] = $row;
                        $this->seedQuantManual($bulan,$tahun,$div,$kpi,$user,(float)$target,(float)$real);
                    }

                    // Lanjut ke bulan berikutnya (jangan jalankan pola rotasi/distribusi utk Aug 2025)
                    continue;
                }

                // ====== BULAN LAIN: pakai distribusi/rotasi seperti biasa ======
                // Ambil distribusi target per divisi/periode (jika ada)
                $distTst = KpiDivisiDistribution::where(['division_id'=>$tstDiv->id,'bulan'=>$bulan,'tahun'=>$tahun])->first();
                $distCsa = KpiDivisiDistribution::where(['division_id'=>$csaDiv->id,'bulan'=>$bulan,'tahun'=>$tahun])->first();
                $distCd  = KpiDivisiDistribution::where(['division_id'=>$cdDiv->id ,'bulan'=>$bulan,'tahun'=>$tahun])->first();

                // TST: rotasi >,=,< antar 3 orang
                $this->seedRealForDivisionMonth(
                    $bulan,$tahun,$tstDiv,$kpiTst,$distTst,$tstUsers,
                    $this->rolesForThree($idx)
                );

                // CSA: 1 orang — mix siklik > = <
                $roleSingle = ['GT','EQ','LT'][$idx % 3];
                $this->seedRealForDivisionMonth(
                    $bulan,$tahun,$csaDiv,$kpiCsa,$distCsa,$csaUsers,
                    [$roleSingle]
                );

                // Creatif Desain: rotasi >,=,< antar 3 orang
                $this->seedRealForDivisionMonth(
                    $bulan,$tahun,$cdDiv,$kpiCd,$distCd,$cdUsers,
                    $this->rolesForThree($idx)
                );
            }
        });
    }

    /**
     * Seed satu KPI-kuantitatif untuk satu divisi & bulan.
     * Jika distribusi tidak ada, fallback pembagian **integer** (largest remainder) agar konsisten.
     */
    private function seedRealForDivisionMonth(
        int $bulan, int $tahun, Division $div, KpiDivisi $kpi, ?KpiDivisiDistribution $dist,
        $users, array $roles
    ): void {
        if ($users->count() === 0) return;

        // Ambil target per user
        $targets = [];
        if ($dist) {
            // Pakai yang ada di tabel distribusi; kalau kosong/kurang, fallback integer split
            $items = KpiDivisiDistributionItem::where('distribution_id',$dist->id)
                    ->where('kpi_divisi_id',$kpi->id)
                    ->whereIn('user_id', $users->pluck('id'))
                    ->get()
                    ->keyBy('user_id');

            if ($items->count() === $users->count()) {
                foreach ($users as $u) {
                    $targets[$u->id] = (int) round((float)($items[$u->id]->target ?? 0));
                }
            } else {
                $targets = $this->integerSplit((int) round($kpi->target), $users->pluck('id')->all());
            }
        } else {
            $targets = $this->integerSplit((int) round($kpi->target), $users->pluck('id')->all());
        }

        // Buat per user (realisasi GT/EQ/LT)
        foreach ($users as $i => $u) {
            $role = $roles[$i % count($roles)] ?? 'EQ';
            $tgt  = (float)($targets[$u->id] ?? 0);
            $real = $this->makeRealization($tgt, $role);

            $score = $this->scoreKuantitatif($real, $tgt);

            // Header
            $hdr = KpiDivisiKuantitatifRealization::updateOrCreate(
                ['user_id'=>$u->id,'division_id'=>$div->id,'bulan'=>$bulan,'tahun'=>$tahun],
                ['status'=>'approved','hr_note'=>null,'total_score'=>null]
            );

            // Clear items & insert baru
            KpiDivisiKuantitatifRealizationItem::where('realization_id',$hdr->id)->delete();

            KpiDivisiKuantitatifRealizationItem::create([
                'realization_id' => $hdr->id,
                'user_id'        => $u->id,
                'kpi_divisi_id'  => $kpi->id,
                'target'         => $tgt,  // integer
                'realization'    => $real,
                'score'          => $score,
            ]);

            // total_score header = bobot KPI * skor
            $total = round(((float)$kpi->bobot) * $score, 2);
            $hdr->update(['total_score'=>$total]);
        }
    }

    /** Versi manual: set Target & Realisasi eksplisit untuk 1 user di 1 periode */
    private function seedQuantManual(
        int $bulan, int $tahun, Division $div, KpiDivisi $kpi, User $user, float $target, float $real
    ): void {
        $score = $this->scoreKuantitatif($real, $target);

        // Header
        $hdr = KpiDivisiKuantitatifRealization::updateOrCreate(
            ['user_id'=>$user->id,'division_id'=>$div->id,'bulan'=>$bulan,'tahun'=>$tahun],
            ['status'=>'approved','hr_note'=>null,'total_score'=>null]
        );

        // Clear items & insert baru
        KpiDivisiKuantitatifRealizationItem::where('realization_id',$hdr->id)->delete();

        KpiDivisiKuantitatifRealizationItem::create([
            'realization_id' => $hdr->id,
            'user_id'        => $user->id,
            'kpi_divisi_id'  => $kpi->id,
            'target'         => (float)$target,
            'realization'    => (float)$real,
            'score'          => $score,
        ]);

        // total_score header = bobot KPI * skor (karena 1 KPI kuantitatif per divisi)
        $hdr->update(['total_score' => round(((float)$kpi->bobot) * $score, 2)]);
    }

    /** Bagi T ke N user (id terurut) dengan largest remainder → hasil integer & jumlah = T */
    private function integerSplit(int $T, array $userIds): array
    {
        $n = max(1, count($userIds));
        $base = intdiv($T, $n);
        $rem  = $T - ($base * $n);

        $out = [];
        foreach ($userIds as $idx => $uid) {
            $out[$uid] = $base + ($idx < $rem ? 1 : 0);
        }
        return $out;
    }

    /** Roles rotasi 3 orang per-bulan: >,=,< lalu bergeser */
    private function rolesForThree(int $monthIndex): array
    {
        $base = ['GT','EQ','LT'];
        $shift = $monthIndex % 3;
        return array_merge(array_slice($base,$shift), array_slice($base,0,$shift));
    }

    /** Generator realisasi: GT (120%), EQ (100%), LT (75%) — dibulatkan ke integer natural */
    private function makeRealization(float $target, string $role): float
    {
        $role = strtoupper($role);
        if ($role === 'GT') return (float)max(0, (int) round($target * 1.20));
        if ($role === 'LT') return (float)max(0, (int) floor($target * 0.75));
        return (float)((int) round($target));
    }

    /** Skor kuantitatif = linear ≥ target (cap 200), fuzzy < target — sama dgn controller */
    private function scoreKuantitatif(float $real, float $target): float
    {
        if ($target <= 0) return $real > 0 ? 150.0 : 0.0;
        if ($real >= $target) return min(200.0, round(100.0 * ($real / $target), 2));

        $ratio = max(0.0, min(1.0, $real / $target));
        return round($this->fuzzyBelowTargetScore($ratio), 2);
    }

    private function fuzzyBelowTargetScore(float $x): float
    {
        $muL = 0.0; $muM = 0.0; $muH = 0.0;

        if     ($x <= 0.3) $muL = ($x - 0.0) / (0.3 - 0.0 + 1e-9);
        elseif ($x <= 0.6) $muL = (0.6 - $x) / (0.6 - 0.3 + 1e-9);
        $muL = max(0.0, min(1.0, $muL));

        if     ($x <= 0.4) $muM = 0.0;
        elseif ($x <= 0.7) $muM = ($x - 0.4) / (0.7 - 0.4 + 1e-9);
        else               $muM = (1.0 - $x) / (1.0 - 0.7 + 1e-9);
        $muM = max(0.0, min(1.0, $muM));

        if     ($x <= 0.6) $muH = 0.0;
        elseif ($x <= 0.9) $muH = ($x - 0.6) / (0.9 - 0.6 + 1e-9);
        else               $muH = (1.0 - $x) / (1.0 - 0.9 + 1e-9);
        $muH = max(0.0, min(1.0, $muH));

        $wL = 50; $wM = 80; $wH = 95;
        $den = $muL + $muM + $muH;
        if ($den <= 0) return 0.0;
        return ($muL*$wL + $muM*$wM + $muH*$wH) / $den;
    }

    /** List [[bulan,tahun], ...] inklusif */
    private function monthRange(int $y1,int $m1,int $y2,int $m2): array
    {
        $out=[]; $y=$y1; $m=$m1;
        while ($y<$y2 || ($y===$y2 && $m<=$m2)) {
            $out[] = [$m,$y];
            $m++; if ($m>12){ $m=1; $y++; }
        }
        return $out;
    }
}
