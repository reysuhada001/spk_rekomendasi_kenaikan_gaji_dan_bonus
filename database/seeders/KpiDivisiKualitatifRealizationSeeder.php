<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Division;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiKualitatifRealizationItem;

class KpiDivisiKualitatifRealizationSeeder extends Seeder
{
    public function run(): void
    {
        $tstDiv = Division::where('name','like','%Technical Support Team%')->first();
        $csaDiv = Division::where('name','like','%Chat Sales Agent%')->first();
        $cdDiv  = Division::where('name','like','%Creatif Desain%')->first();
        if (!$tstDiv || !$csaDiv || !$cdDiv) {
            throw new \RuntimeException('Seed Division dulu (TST, CSA, Creatif Desain).');
        }

        $tstUsers = User::where('division_id',$tstDiv->id)->where('role','karyawan')->orderBy('full_name')->get()->values();
        $csaUsers = User::where('division_id',$csaDiv->id)->where('role','karyawan')->orderBy('full_name')->get()->values();
        $cdUsers  = User::where('division_id',$cdDiv->id)->where('role','karyawan')->orderBy('full_name')->get()->values();

        $months = $this->monthRange(2024,1, 2025,8);

        DB::transaction(function() use ($months,$tstDiv,$csaDiv,$cdDiv,$tstUsers,$csaUsers,$cdUsers) {
            foreach ($months as $idx => [$bulan,$tahun]) {
                // Pastikan KPI kualitatif ada per divisi/periode
                $kpiTst = KpiDivisi::firstOrCreate(
                    ['division_id'=>$tstDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,'tipe'=>'kualitatif','nama'=>'Tingkat Kepuasan Pengguna Terhadap Pelayanan'],
                    ['satuan'=>'Point','target'=>85,'bobot'=>0.0625] // sesuai TST
                );
                $kpiCsa = KpiDivisi::firstOrCreate(
                    ['division_id'=>$csaDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,'tipe'=>'kualitatif','nama'=>'Kualitas Interaksi Chat dengan Pelanggan'],
                    ['satuan'=>'Point','target'=>85,'bobot'=>0.25] // default equal type
                );
                $kpiCd = KpiDivisi::firstOrCreate(
                    ['division_id'=>$cdDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,'tipe'=>'kualitatif','nama'=>'Kualitas Hasil Desain'],
                    ['satuan'=>'Point','target'=>85,'bobot'=>0.25] // Creatif: 0.25
                );

                // ====== OVERRIDE KHUSUS: Agustus 2025 pakai realisasi manual ======
                if ($bulan === 8 && $tahun === 2025) {
                    // --- Ambil user per nama (pastikan ejaan sama dgn DB) ---
                    $handika = $tstUsers->firstWhere('full_name', 'Moh. Handika Nurfadli');
                    $devan   = $tstUsers->firstWhere('full_name', 'Devan Aditya Halimawan');
                    $rizal   = $tstUsers->firstWhere('full_name', 'Muh. Rizal Fauzi');

                    $ariyani = $csaUsers->firstWhere('full_name', 'Ariyani');

                    // Catatan: sesuaikan ejaan "Al/AL" dengan data di DB
                    $akmal = $cdUsers->firstWhere('full_name', 'Muh. Akmal Al Fazar')
                           ?? $cdUsers->firstWhere('full_name', 'Muh. Akmal AL Fazar');
                    $ratna = $cdUsers->firstWhere('full_name', 'Ratna Damayanti');
                    $laela = $cdUsers->firstWhere('full_name', 'Laela Nurul Fadhilah');

                    foreach ([
                        // TST (Target 85, Realisasi sesuai tabel)
                        [$handika, $tstDiv, $kpiTst, 88],
                        [$devan,   $tstDiv, $kpiTst, 85],
                        [$rizal,   $tstDiv, $kpiTst, 82],
                        // CSA
                        [$ariyani, $csaDiv, $kpiCsa, 90],
                        // Creatif Desain
                        [$akmal,   $cdDiv,  $kpiCd,  85],
                        [$ratna,   $cdDiv,  $kpiCd,  82],
                        [$laela,   $cdDiv,  $kpiCd,  90],
                    ] as $row) {
                        if (!$row[0]) {
                            throw new \RuntimeException('User tidak ditemukan untuk override Agustus 2025 (periksa ejaan nama).');
                        }
                        [$user,$div,$kpi,$real] = $row;
                        $this->seedKualManual($bulan,$tahun,$div,$kpi,$user,(float)$real);
                    }

                    // Lanjut ke bulan berikutnya (jangan jalankan pola rotasi utk Aug 2025)
                    continue;
                }

                // ====== Pola rotasi per divisi (untuk bulan selain Agustus 2025) ======
                // TST & Creatif: rotasi > = < ; CSA: single mix (> = <)
                $roles3 = $this->rolesForThree($idx);
                $role1  = ['GT','EQ','LT'][$idx % 3];

                $this->seedKualForDivision($bulan,$tahun,$tstDiv,$kpiTst,$tstUsers,$roles3);
                $this->seedKualForDivision($bulan,$tahun,$cdDiv ,$kpiCd ,$cdUsers ,$roles3);
                $this->seedKualForDivision($bulan,$tahun,$csaDiv,$kpiCsa,$csaUsers,[$role1]);
            }
        });
    }

    private function seedKualForDivision(int $bulan,int $tahun, Division $div, KpiDivisi $kpi, $users, array $roles): void
    {
        if ($users->isEmpty()) return;
        foreach ($users as $i => $u) {
            $role = $roles[$i % count($roles)] ?? 'EQ';
            $tgt  = (float)$kpi->target;
            // Kualitatif: lebih besar lebih baik
            $real = $this->realByRoleLargerBetter($tgt, $role);

            $score = $this->scoreKualitatif($real, $tgt);

            $hdr = KpiDivisiKualitatifRealization::updateOrCreate(
                ['user_id'=>$u->id,'division_id'=>$div->id,'bulan'=>$bulan,'tahun'=>$tahun],
                ['status'=>'approved','hr_note'=>null,'total_score'=>null]
            );

            KpiDivisiKualitatifRealizationItem::where('realization_id',$hdr->id)->delete();
            KpiDivisiKualitatifRealizationItem::create([
                'realization_id'=>$hdr->id,
                'user_id'       =>$u->id,
                'kpi_divisi_id' =>$kpi->id,
                'target'        =>$tgt,
                'realization'   =>$real,
                'score'         =>$score,
            ]);

            $hdr->update(['total_score'=> round(((float)$kpi->bobot) * $score, 2)]);
        }
    }

    /** Versi manual: set realisasi eksplisit untuk 1 user di 1 periode */
    private function seedKualManual(int $bulan,int $tahun, Division $div, KpiDivisi $kpi, User $user, float $real): void
    {
        $tgt   = (float)$kpi->target;
        $score = $this->scoreKualitatif($real, $tgt);

        $hdr = KpiDivisiKualitatifRealization::updateOrCreate(
            ['user_id'=>$user->id,'division_id'=>$div->id,'bulan'=>$bulan,'tahun'=>$tahun],
            ['status'=>'approved','hr_note'=>null,'total_score'=>null]
        );

        KpiDivisiKualitatifRealizationItem::where('realization_id',$hdr->id)->delete();
        KpiDivisiKualitatifRealizationItem::create([
            'realization_id'=>$hdr->id,
            'user_id'       =>$user->id,
            'kpi_divisi_id' =>$kpi->id,
            'target'        =>$tgt,
            'realization'   =>$real,
            'score'         =>$score,
        ]);

        $hdr->update(['total_score'=> round(((float)$kpi->bobot) * $score, 2)]);
    }

    private function realByRoleLargerBetter(float $target, string $role): float
    {
        $role = strtoupper($role);
        return match($role) {
            'GT' => round($target * 1.12, 2), // sedikit di atas target
            'LT' => round($target * 0.80, 2), // di bawah target
            default => round($target, 2),
        };
    }

    // === Rumus skor (identik cara controller kualitatif) ===
    private function scoreKualitatif(float $real, float $target): float
    {
        if ($target <= 0) return $real > 0 ? 150.0 : 0.0;
        if ($real >= $target) return min(200.0, round(100.0 * ($real / $target), 2));
        $ratio = max(0.0, min(1.0, $real / $target));
        return round($this->fuzzyBelowTarget($ratio), 2);
    }

    private function fuzzyBelowTarget(float $x): float
    {
        $muL=$muM=$muH=0.0;

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

        $wL=50; $wM=80; $wH=95;
        $den = $muL + $muM + $muH;
        if ($den <= 0) return 0.0;
        return ($muL*$wL + $muM*$wM + $muH*$wH) / $den;
    }

    private function monthRange(int $y1,int $m1,int $y2,int $m2): array
    {
        $out=[]; $y=$y1; $m=$m1;
        while ($y<$y2 || ($y===$y2 && $m<=$m2)) { $out[]=[$m,$y]; $m++; if($m>12){$m=1;$y++;} }
        return $out;
    }

    private function rolesForThree(int $monthIndex): array
    {
        $base=['GT','EQ','LT']; $shift=$monthIndex%3;
        return array_merge(array_slice($base,$shift), array_slice($base,0,$shift));
    }
}
