<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\AhpGlobalWeight;

// KPI Umum
use App\Models\KpiUmumRealization;

// KPI Divisi (4 tipe)
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiPersentaseRealization;

// Peer (locked)
use App\Models\PeerAssessment;
use App\Models\PeerAssessmentItem;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class SalaryRaiseRecommendationController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    // Ambang & rentang persen kenaikan (kontinu/partial seperti bonus)
    private float $BAIK_MAX = 115.0;              // batas atas "Baik"
    private float $SANGAT_BAIK_START = 116.0;     // mulai "Sangat Baik"
    private float $SANGAT_BAIK_LINEAR_TO = 135.0; // titik jenuh linear

    private float $PCT_BAIK_MIN = 3.0;            // Baik: 3% → 6%
    private float $PCT_BAIK_MAX = 6.0;
    private float $PCT_SANGAT_MIN = 6.0;          // Sangat Baik: 6% → 10%
    private float $PCT_SANGAT_MAX = 10.0;

    public function index(Request $request)
    {
        $me      = Auth::user();
        $perPage = (int) $request->input('per_page', 10);
        $search  = trim($request->input('search',''));
        $tahun   = $request->filled('tahun') ? (int)$request->tahun : null;
        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;

        $usersQ = User::with('division')->where('role','karyawan');
        if (in_array($me->role, ['owner','hr'], true)) {
            if (!empty($division_id)) $usersQ->where('division_id',$division_id);
        } elseif ($me->role === 'leader') {
            $usersQ->where('division_id',$me->division_id);
        } else {
            $usersQ->where('id',$me->id);
        }
        if ($search !== '') {
            $usersQ->where(function($q) use ($search){
                $q->where('full_name','like',"%{$search}%")
                  ->orWhere('username','like',"%{$search}%")
                  ->orWhere('email','like',"%{$search}%");
            });
        }

        if (is_null($tahun)) {
            $users = $usersQ->whereRaw('1=0')->paginate($perPage)->appends($request->all());
            return view('salary-raise.index', [
                'me'=>$me,'users'=>$users,'tahun'=>$tahun,'division_id'=>$division_id,
                'divisions'=>Division::orderBy('name')->get(),
                'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
                'hasGlobalWeights'=>$this->hasGlobalWeights(),
                'weights'=>$this->getGlobalWeights(),
                'rows'=>[],
            ]);
        }

        $users = $usersQ->orderBy('full_name')->paginate($perPage)->appends($request->all());

        [$wU, $wD, $wP] = $this->getGlobalWeights();
        $hasGlobal = $this->hasGlobalWeights();

        $rows = [];
        foreach ($users as $u) {
            $peerAnnual = $this->peerAnnualScore($u->id, $u->division_id, $tahun); // 0..100 atau null

            $sumMonthly = 0.0;
            for ($m=1; $m<=12; $m++) {
                $u_m = $this->kpiUmumMonthly($u->id, $m, $tahun);                    // 0..200 atau null
                $d_m = $this->kpiDivisiMonthly($u->id, $u->division_id, $m, $tahun);  // 0..200 atau null (FULL SUM)
                $p_m = $peerAnnual; // peer tahunan konstan per-bulan

                // Renormalisasi bobot per-bulan (jika ada komponen null)
                $weights = $this->renormalizeWeights(
                    ['u'=>$u_m,'d'=>$d_m,'p'=>$p_m],
                    ['u'=>$wU, 'd'=>$wD, 'p'=>$wP]
                );

                $finalMonth = 0.0;
                if ($u_m !== null) $finalMonth += $weights['u'] * $u_m;
                if ($d_m !== null) $finalMonth += $weights['d'] * $d_m;
                if ($p_m !== null) $finalMonth += $weights['p'] * $p_m;

                $sumMonthly += $finalMonth;
            }

            // Skor tahunan = rata-rata 12 bulan
            $final = round($sumMonthly / 12.0, 2);

            // PARTIAL seperti bonus → 1 angka persen (bukan rentang statis)
            [$label, $pct] = $this->raiseBandPartial($final);

            $rows[$u->id] = [
                'final'     => $final,
                'label'     => $label,
                'raise_pct' => $pct,                 // angka, mis. 7.35
                'range'     => rtrim(rtrim(number_format($pct,2,'.',''), '0'), '.').'%', // kompatibel view lama
            ];
        }

        return view('salary-raise.index', [
            'me'=>$me,'users'=>$users,'tahun'=>$tahun,'division_id'=>$division_id,
            'divisions'=>Division::orderBy('name')->get(),
            'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
            'hasGlobalWeights'=>$hasGlobal,
            'weights'=>[$wU,$wD,$wP],
            'rows'=>$rows,
        ]);
    }

    /* ==================== HELPERS ==================== */

    private function hasGlobalWeights(): bool
    {
        return AhpGlobalWeight::query()->exists();
    }

    /** Ambil bobot global terakhir, default 1/3 bila kosong. */
    private function getGlobalWeights(): array
    {
        $w = AhpGlobalWeight::query()->latest()->first();
        if (!$w) return [1/3,1/3,1/3];

        // kolom sesuai controller AHP Global Anda
        $u = (float)($w->w_kpi_umum   ?? 0);
        $d = (float)($w->w_kpi_divisi ?? 0);
        $p = (float)($w->w_peer       ?? 0);
        $sum = $u + $d + $p;
        if ($sum <= 0) return [1/3,1/3,1/3];

        return [$u/$sum, $d/$sum, $p/$sum];
    }

    /** Renormalisasi bobot bila ada nilai null (tiap bulan). */
    private function renormalizeWeights(array $values, array $weights): array
    {
        $active = ['u'=>0.0,'d'=>0.0,'p'=>0.0];
        $sum = 0.0;
        foreach ($values as $k=>$v) {
            if ($v !== null) { $active[$k] = $weights[$k]; $sum += $weights[$k]; }
        }
        if ($sum <= 0) return ['u'=>1/3,'d'=>1/3,'p'=>1/3];
        foreach ($active as $k=>$w) $active[$k] = $w / $sum;
        return $active;
    }

    /** KPI Umum bulan tertentu (approved) → total_score atau null. */
    private function kpiUmumMonthly(int $userId, int $bulan, int $tahun): ?float
    {
        $row = KpiUmumRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->first();

        if (!$row) return null;
        return $row->total_score !== null ? (float)$row->total_score : null;
    }

    /**
     * KPI Divisi bulan tertentu (FULL SUM, bukan rata-rata):
     * - Kuantitatif/Kualitatif/Response: pakai total_score (sudah Σ w_i * S_i).
     * - Persentase: Σ(score × bobot KPI) untuk semua KPI persentase divisi pada bulan tsb.
     * Hasil adalah skor Divisi bulanan komposit 0..~200.
     */
    private function kpiDivisiMonthly(int $userId, ?int $divisionId, int $bulan, int $tahun): ?float
    {
        if (!$divisionId) return null;

        $hasAny = false;
        $total  = 0.0;

        $q = KpiDivisiKuantitatifRealization::where([
            'user_id'=>$userId,'division_id'=>$divisionId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->first();
        if ($q && $q->total_score !== null) { $total += (float)$q->total_score; $hasAny = true; }

        $k = KpiDivisiKualitatifRealization::where([
            'user_id'=>$userId,'division_id'=>$divisionId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->first();
        if ($k && $k->total_score !== null) { $total += (float)$k->total_score; $hasAny = true; }

        $r = KpiDivisiResponseRealization::where([
            'user_id'=>$userId,'division_id'=>$divisionId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->first();
        if ($r && $r->total_score !== null) { $total += (float)$r->total_score; $hasAny = true; }

        // Persentase: SUM semua KPI persentase divisi (score × bobot KPI)
        $prs = KpiDivisiPersentaseRealization::with('kpi')->where([
            'division_id'=>$divisionId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->get();
        if ($prs->isNotEmpty()) {
            foreach ($prs as $p) {
                if ($p->score !== null) {
                    $w = (float) ($p->kpi?->bobot ?? 1.0);
                    $total += (float)$p->score * $w;
                    $hasAny = true;
                }
            }
        }

        return $hasAny ? $total : null;
    }

    /** Peer tahunan → rata-rata item (1..10) dikonversi 0..100, atau null jika tidak ada/ tidak locked. */
    private function peerAnnualScore(int $userId, ?int $divisionId, int $tahun): ?float
    {
        $assessments = PeerAssessment::where('assessee_id',$userId)
            ->when($divisionId, fn($q)=>$q->where('division_id',$divisionId))
            ->where('tahun',$tahun)
            ->when(Schema::hasColumn('peer_assessments','status'), fn($q)=>$q->where('status','locked'))
            ->pluck('id');

        if ($assessments->isEmpty()) return null;

        $scores = PeerAssessmentItem::whereIn('assessment_id',$assessments)->pluck('score');
        if ($scores->isEmpty()) return null;

        $avg10 = (float)$scores->avg();
        return $avg10 * 10.0; // 0..100
    }

    /** Label & persen kenaikan gaji (kontinu/partial seperti bonus) */
    private function raiseBandPartial(float $final): array
    {
        if ($final <= 100.0) {
            return ['Kurang', 0.0];
        }

        if ($final <= $this->BAIK_MAX) {
            // Linear 101..BAIK_MAX → 3%..6%
            $lo = 101.0; $hi = $this->BAIK_MAX;
            $pct = $this->PCT_BAIK_MIN
                 + (($final - $lo) / max(1e-9, ($hi - $lo)))
                 * ($this->PCT_BAIK_MAX - $this->PCT_BAIK_MIN);
            $pct = max($this->PCT_BAIK_MIN, min($this->PCT_BAIK_MAX, $pct));
            return ['Baik', round($pct, 2)];
        }

        // Sangat Baik: linear dari SANGAT_BAIK_START..SANGAT_BAIK_LINEAR_TO → 6%..10%
        $lo = $this->SANGAT_BAIK_START; $hi = $this->SANGAT_BAIK_LINEAR_TO;
        $base = max($lo, min($hi, $final));
        $pct = $this->PCT_SANGAT_MIN
             + (($base - $lo) / max(1e-9, ($hi - $lo)))
             * ($this->PCT_SANGAT_MAX - $this->PCT_SANGAT_MIN);
        $pct = max($this->PCT_SANGAT_MIN, min($this->PCT_SANGAT_MAX, $pct));
        return ['Sangat Baik', round($pct, 2)];
    }
}
