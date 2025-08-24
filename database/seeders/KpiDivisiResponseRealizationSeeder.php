<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Division;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiResponseRealizationItem;

class KpiDivisiResponseRealizationSeeder extends Seeder
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

                // KPI response per divisi/periode
                $kpiTst = KpiDivisi::firstOrCreate(
                    ['division_id'=>$tstDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,'tipe'=>'response','nama'=>'Rata-Rata Waktu Respon Pelayanan'],
                    ['satuan'=>'Menit','target'=>15,'bobot'=>0.1875] // TST
                );
                $kpiCsa = KpiDivisi::firstOrCreate(
                    ['division_id'=>$csaDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,'tipe'=>'response','nama'=>'Rata-Rata Waktu Respon Chat Masuk'],
                    ['satuan'=>'Detik','target'=>60,'bobot'=>0.25] // CSA default
                );
                $kpiCd = KpiDivisi::firstOrCreate(
                    ['division_id'=>$cdDiv->id,'bulan'=>$bulan,'tahun'=>$tahun,'tipe'=>'response','nama'=>'Rata-Rata Waktu Penyelesaian Permintaan Desain'],
                    ['satuan'=>'Hari','target'=>2,'bobot'=>0.125] // Creatif
                );

                // ====== OVERRIDE KHUSUS: Agustus 2025 (target & realisasi manual) ======
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
                        // TST (target 15 menit)
                        [$handika, $tstDiv, $kpiTst, 15, 12],
                        [$devan,   $tstDiv, $kpiTst, 15, 15],
                        [$rizal,   $tstDiv, $kpiTst, 15, 18],

                        // CSA (target 85 detik — override target default 60)
                        [$ariyani, $csaDiv, $kpiCsa, 85, 92],

                        // Creatif Desain (target 2 hari)
                        [$akmal,   $cdDiv,  $kpiCd,  2,  2.0],
                        [$ratna,   $cdDiv,  $kpiCd,  2,  2.5],
                        [$laela,   $cdDiv,  $kpiCd,  2,  1.5],
                    ] as $row) {
                        if (!$row[0]) {
                            throw new \RuntimeException('User tidak ditemukan untuk override Agustus 2025 (periksa ejaan nama).');
                        }
                        [$user,$div,$kpi,$target,$real] = $row;
                        $this->seedRespManual($bulan,$tahun,$div,$kpi,$user,(float)$target,(float)$real);
                    }

                    // skip pola rotasi utk Agustus 2025
                    continue;
                }

                // ====== BULAN LAIN: pola rotasi (TST & CD rotasi 3 orang; CSA single mix) ======
                $roles3 = $this->rolesForThree($idx);
                $role1  = ['GT','EQ','LT'][$idx % 3];

                $this->seedRespForDivision($bulan,$tahun,$tstDiv,$kpiTst,$tstUsers,$roles3);
                $this->seedRespForDivision($bulan,$tahun,$cdDiv ,$kpiCd ,$cdUsers ,$roles3);
                $this->seedRespForDivision($bulan,$tahun,$csaDiv,$kpiCsa,$csaUsers,[$role1]);
            }
        });
    }

    private function seedRespForDivision(int $bulan,int $tahun, Division $div, KpiDivisi $kpi, $users, array $roles): void
    {
        if ($users->isEmpty()) return;

        foreach ($users as $i => $u) {
            $role = $roles[$i % count($roles)] ?? 'EQ';
            $tgt  = (float)$kpi->target;

            // Response: lebih kecil lebih baik
            $real = $this->realByRoleSmallerBetter($tgt, $role);

            $score = $this->scoreResponse($real, $tgt);

            $hdr = KpiDivisiResponseRealization::updateOrCreate(
                ['user_id'=>$u->id,'division_id'=>$div->id,'bulan'=>$bulan,'tahun'=>$tahun],
                ['status'=>'approved','hr_note'=>null,'total_score'=>null]
            );

            KpiDivisiResponseRealizationItem::where('realization_id',$hdr->id)->delete();
            KpiDivisiResponseRealizationItem::create([
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

    /** Versi manual: set TARGET & REALISASI eksplisit (override per user) */
    private function seedRespManual(
        int $bulan,int $tahun, Division $div, KpiDivisi $kpi, User $user, float $target, float $real
    ): void {
        $score = $this->scoreResponse($real, $target);

        $hdr = KpiDivisiResponseRealization::updateOrCreate(
            ['user_id'=>$user->id,'division_id'=>$div->id,'bulan'=>$bulan,'tahun'=>$tahun],
            ['status'=>'approved','hr_note'=>null,'total_score'=>null]
        );

        KpiDivisiResponseRealizationItem::where('realization_id',$hdr->id)->delete();
        KpiDivisiResponseRealizationItem::create([
            'realization_id'=>$hdr->id,
            'user_id'       =>$user->id,
            'kpi_divisi_id' =>$kpi->id,
            'target'        =>$target,
            'realization'   =>$real,
            'score'         =>$score,
        ]);

        $hdr->update(['total_score'=> round(((float)$kpi->bobot) * $score, 2)]);
    }

    private function realByRoleSmallerBetter(float $target, string $role): float
    {
        $role = strtoupper($role);
        return match($role) {
            'GT' => max(0.1, round($target * 0.80, 2)), // lebih cepat (lebih kecil)
            'LT' => round($target * 1.30, 2),           // lebih lambat (lebih besar)
            default => round($target, 2),               // = target
        };
    }

    // === Rumus skor (identik controller response) ===
    private function scoreResponse(float $real, float $target): float
    {
        if ($target <= 0) return 0.0;
        if ($real <= $target) return min(200.0, round(100.0 * ($target / max($real,1e-9)), 2));
        $ratio = max(0.0, min(1.0, $target / max($real,1e-9)));
        return round($this->fuzzyPenaltySlow($ratio), 2);
    }

    private function fuzzyPenaltySlow(float $x): float
    {
        $muL=0.0; $muM=0.0; $muH=0.0;

        if     ($x <= 0.3) $muL = 1.0;
        elseif ($x <= 0.6) $muL = (0.6 - $x) / (0.6 - 0.3 + 1e-9);

        if     ($x <= 0.4) $muM = ($x - 0.1) / (0.4 - 0.1 + 1e-9);
        elseif ($x <= 0.8) $muM = (0.8 - $x) / (0.8 - 0.4 + 1e-9);
        $muM = max(0.0, min(1.0, $muM));

        if     ($x <= 0.6) $muH = 0.0;
        elseif ($x <= 0.9) $muH = ($x - 0.6) / (0.9 - 0.6 + 1e-9);
        else               $muH = 1.0;

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
