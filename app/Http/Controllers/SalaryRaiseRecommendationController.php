<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\AhpGlobalWeight;

// KPI Umum (bulanan, approved -> total_score)
use App\Models\KpiUmumRealization;

// KPI Divisi (empat tipe, approved -> gabungan equal-weight antar tipe yang tersedia)
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiResponseRealization;
// Persentase: per KPI-divisi (approved) lalu diproyeksikan sama untuk semua karyawan divisi tsb
use App\Models\KpiDivisiPersentaseRealization;

// Peer assessment (locked)
use App\Models\PeerAssessment;
use App\Models\PeerAssessmentItem;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalaryRaiseRecommendationController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    public function index(Request $request)
    {
        $me      = Auth::user();
        $perPage = (int) $request->input('per_page', 10);
        $search  = trim($request->input('search',''));
        $tahun   = $request->filled('tahun') ? (int)$request->tahun : null;
        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;

        // role-based scope
        $usersQ = User::with('division')->where('role','karyawan');

        if (in_array($me->role, ['owner','hr'], true)) {
            if (!empty($division_id)) $usersQ->where('division_id',$division_id);
        } elseif ($me->role === 'leader') {
            $usersQ->where('division_id', $me->division_id);
        } else { // karyawan
            $usersQ->where('id', $me->id);
        }

        if ($search !== '') {
            $usersQ->where(function($q) use ($search){
                $q->where('full_name','like',"%{$search}%")
                  ->orWhere('username','like',"%{$search}%")
                  ->orWhere('email','like',"%{$search}%");
            });
        }

        // Jika tahun belum dipilih, kosongkan data (UX sama seperti halaman bonus)
        if (is_null($tahun)) {
            $users = $usersQ->whereRaw('1=0')->paginate($perPage)->appends($request->all());
            return view('salary-raise.index', [
                'me'=>$me,'users'=>$users,'tahun'=>$tahun,'division_id'=>$division_id,
                'divisions'=>Division::orderBy('name')->get(),
                'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
                'hasGlobalWeights'=>$this->hasGlobalWeights(),
                'weights'=>$this->getGlobalWeights(), // utk header info
                'rows'=>[], // tidak ada data
            ]);
        }

        $users = $usersQ->orderBy('full_name')->paginate($perPage)->appends($request->all());

        // Ambil bobot global (fallback 1/3,1/3,1/3 bila kosong)
        [$wU, $wD, $wP] = $this->getGlobalWeights();
        $hasGlobal = $this->hasGlobalWeights();

        // Hitung per user
        $rows = [];
        foreach ($users as $u) {
            // Rata-rata tahunan tiap komponen (0..100), null jika tidak ada satupun bulan
            $U = $this->avgYearlyKpiUmum($u->id, $tahun);
            $D = $this->avgYearlyKpiDivisi($u->id, $u->division_id, $tahun);
            $P = $this->avgYearlyPeer($u->id, $u->division_id, $tahun);

            // final = wU*U + wD*D + wP*P (null diperlakukan 0)
            $Uf = $U['avg'] ?? 0.0;
            $Df = $D['avg'] ?? 0.0;
            $Pf = $P['avg'] ?? 0.0;

            $final = round($wU*$Uf + $wD*$Df + $wP*$Pf, 2);

            // label & rekomendasi persen
            [$label, $percentRange] = $this->raiseBand($final);

            $rows[$u->id] = [
                'u_avg' => $U['avg'], 'u_cnt' => $U['cnt'],
                'd_avg' => $D['avg'], 'd_cnt' => $D['cnt'],
                'p_avg' => $P['avg'], 'p_cnt' => $P['cnt'],
                'final' => $final,
                'label' => $label,
                'range' => $percentRange,
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

    private function getGlobalWeights(): array
    {
        $w = AhpGlobalWeight::query()->latest()->first();
        if (!$w) return [1/3, 1/3, 1/3];

        $u = (float)($w->kpi_umum ?? 0);
        $d = (float)($w->kpi_divisi ?? 0);
        $p = (float)($w->peer ?? 0);
        $sum = $u + $d + $p;
        if ($sum <= 0) return [1/3, 1/3, 1/3];

        return [$u/$sum, $d/$sum, $p/$sum];
    }

    /** KPI Umum – rata-rata tahunan (approved) */
    private function avgYearlyKpiUmum(int $userId, int $tahun): array
    {
        $rows = KpiUmumRealization::where('user_id',$userId)
            ->where('tahun',$tahun)
            ->where('status','approved')
            ->pluck('total_score');

        if ($rows->isEmpty()) return ['avg'=>null,'cnt'=>0];
        return ['avg'=>round((float)$rows->avg(), 2), 'cnt'=>$rows->count()];
    }

    /**
     * KPI Divisi – gabungan empat tipe (kuantitatif, kualitatif, response, persentase) per bulan:
     * - Ambil skor tiap tipe (approved) jika ada, lalu rata-kan antar-tipe (equal-weight).
     * - Rata-ratakan hasil bulanan sepanjang tahun.
     */
    private function avgYearlyKpiDivisi(int $userId, ?int $divisionId, int $tahun): array
    {
        if (!$divisionId) return ['avg'=>null,'cnt'=>0];

        $monthly = [];
        for ($m=1; $m<=12; $m++) {
            $scores = [];

            // kuantitatif
            $q = KpiDivisiKuantitatifRealization::where([
                'user_id'=>$userId,'division_id'=>$divisionId,'bulan'=>$m,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
            if ($q && $q->total_score !== null) $scores[] = (float)$q->total_score;

            // kualitatif
            $k = KpiDivisiKualitatifRealization::where([
                'user_id'=>$userId,'division_id'=>$divisionId,'bulan'=>$m,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
            if ($k && $k->total_score !== null) $scores[] = (float)$k->total_score;

            // response
            $r = KpiDivisiResponseRealization::where([
                'user_id'=>$userId,'division_id'=>$divisionId,'bulan'=>$m,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
            if ($r && $r->total_score !== null) $scores[] = (float)$r->total_score;

            // persentase – jika ada realisasi persentase diset per KPI & disetujui (per divisi/periode)
            // kita pakai skor persentase divisi-bulan tsb untuk seluruh karyawan divisi
            $p = KpiDivisiPersentaseRealization::where([
                'division_id'=>$divisionId,'bulan'=>$m,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
            if ($p && $p->score !== null) $scores[] = (float)$p->score;

            if (count($scores)) {
                $monthly[] = array_sum($scores) / count($scores);
            }
        }

        if (empty($monthly)) return ['avg'=>null,'cnt'=>0];

        return ['avg'=>round(array_sum($monthly)/count($monthly), 2), 'cnt'=>count($monthly)];
    }

    /** Peer – rata-rata skor item (1..10) sepanjang tahun (locked), dikonversi ke 0..100 */
    private function avgYearlyPeer(int $userId, ?int $divisionId, int $tahun): array
    {
        // ambil semua assessment yang menilai user ini sepanjang tahun
        $assessments = PeerAssessment::where('assessee_id', $userId)
            ->where('tahun',$tahun)
            ->when($divisionId, fn($q)=>$q->where('division_id',$divisionId))
            ->when($this->peerHasStatus(), fn($q)=>$q->where('status','locked'))
            ->pluck('id');

        if ($assessments->isEmpty()) return ['avg'=>null,'cnt'=>0];

        $scores = PeerAssessmentItem::whereIn('assessment_id',$assessments)->pluck('score');
        if ($scores->isEmpty()) return ['avg'=>null,'cnt'=>0];

        $avg10  = (float)$scores->avg();   // skala 1..10
        $avg100 = $avg10 * 10.0;           // ke 0..100

        return ['avg'=>round($avg100, 2), 'cnt'=>$scores->count()];
    }

    private function peerHasStatus(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn('peer_assessments','status');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Label & range persentase kenaikan gaji */
    private function raiseBand(float $final): array
    {
        if ($final <= 100.0)  return ['Kurang', '0%'];
        if ($final <= 110.0)  return ['Baik',   '1–3%'];
        if ($final <= 120.0)  return ['Baik',   '3–6%'];
        return ['Sangat Baik', '6–10%'];
    }
}
