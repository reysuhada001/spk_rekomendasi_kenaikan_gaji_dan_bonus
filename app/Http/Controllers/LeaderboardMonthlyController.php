<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\KpiUmumRealization;
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiPersentaseRealization;
use App\Models\PeerAssessment;
use App\Models\PeerAssessmentItem;
use App\Models\AhpGlobalWeight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class LeaderboardMonthlyController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    public function __construct()
    {
        $this->middleware(['auth','role:owner,hr,leader,karyawan']);
    }

    public function index(Request $request)
    {
        $me      = Auth::user();
        $perPage = (int)$request->input('per_page', 10);
        $bulan   = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun   = $request->filled('tahun') ? (int)$request->tahun : null;

        // Jika periode belum dipilih, tampilkan tabel kosong
        if (is_null($bulan) || is_null($tahun)) {
            $empty = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path'=>$request->url(), 'query'=>$request->query()
            ]);
            return view('leaderboard-bulanan.index', [
                'me'=>$me, 'users'=>$empty, 'rows'=>[],
                'bulan'=>$bulan, 'tahun'=>$tahun,
                'bulanList'=>$this->bulanList, 'perPage'=>$perPage,
            ]);
        }

        // Seluruh karyawan (leaderboard global lintas divisi)
        $karyawan = User::with('division')
            ->where('role','karyawan')
            ->orderBy('full_name')
            ->get();

        // Bobot AHP global (dipakai di final score, seperti halaman bonus)
        [$wUmum, $wDiv, $wPeer] = $this->getGlobalWeights();

        $rows = [];
        foreach ($karyawan as $u) {
            $ku = $this->getKpiUmumScore($u->id, $bulan, $tahun);          // 0..200
            $kd = $this->getKpiDivisiScoreWeighted($u, $bulan, $tahun);    // 0..200 (sudah * bobot tipe)
            $kp = $this->getPeerScore($u->id, $bulan, $tahun);             // 0..100

            // Bobot global dinormalisasi hanya ke komponen yang ada nilainya
            $weights = $this->renormalizeWeights(
                ['umum'=>$ku,'divisi'=>$kd,'peer'=>$kp],
                ['umum'=>$wUmum,'divisi'=>$wDiv,'peer'=>$wPeer]
            );

            // Skor akhir (persis seperti di BonusRecommendationController)
            $final = 0.0;
            if ($ku !== null) $final += $weights['umum']   * $ku;
            if ($kd !== null) $final += $weights['divisi'] * $kd;
            if ($kp !== null) $final += $weights['peer']   * $kp;
            $final = round($final, 4);

            $rows[] = [
                'user_id'  => $u->id,
                'name'     => $u->full_name,
                'division' => $u->division?->name ?? '-',
                'final'    => $final,

                // optional untuk debugging/tampilan
                's_umum'=>$ku,'s_div'=>$kd,'s_peer'=>$kp,
                'w_umum'=>$weights['umum'],'w_div'=>$weights['divisi'],'w_peer'=>$weights['peer'],
            ];
        }

        // Urutkan desc dan beri peringkat (tie-aware)
        usort($rows, function($a,$b){
            if (abs($b['final'] - $a['final']) < 0.0001) return 0;
            return $b['final'] <=> $a['final'];
        });
        $rank=0; $prev=null;
        foreach ($rows as &$r) {
            if ($prev===null || abs($r['final']-$prev)>0.0001) { $rank++; $prev=$r['final']; }
            $r['rank']=$rank;
        }
        unset($r);

        // Pagination manual
        $currentPage = max(1, (int)$request->input('page', 1));
        $slice = array_slice($rows, ($currentPage-1)*$perPage, $perPage);
        $paginator = new LengthAwarePaginator($slice, count($rows), $perPage, $currentPage, [
            'path'=>$request->url(), 'query'=>$request->query(),
        ]);

        return view('leaderboard-bulanan.index', [
            'me'=>$me,
            'users'=>$paginator,
            'rows'=>collect($slice)->keyBy('user_id'),
            'bulan'=>$bulan,
            'tahun'=>$tahun,
            'bulanList'=>$this->bulanList,
            'perPage'=>$perPage,
        ]);
    }

    /* ===================== HELPERS ===================== */

    /** Bobot AHP global; fallback 1/3 masing-masing */
    private function getGlobalWeights(): array
    {
        $w = AhpGlobalWeight::latest()->first();
        if (!$w) return [1/3,1/3,1/3];

        // pakai field yang sama dengan halaman bonus
        $ku = (float)($w->w_kpi_umum   ?? $w->kpi_umum   ?? 0);
        $kd = (float)($w->w_kpi_divisi ?? $w->kpi_divisi ?? 0);
        $kp = (float)($w->w_peer       ?? $w->peer       ?? 0);
        $sum = $ku+$kd+$kp;
        if ($sum <= 0) return [1/3,1/3,1/3];
        return [$ku/$sum, $kd/$sum, $kp/$sum];
    }

    /** Skor KPI Umum approved (0..200) */
    private function getKpiUmumScore(int $userId, int $bulan, int $tahun): ?float
    {
        $r = KpiUmumRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->first();
        return $r ? round((float)$r->total_score, 2) : null;
    }

    /** Bobot tipe per divisi (jumlah=1) — sama dengan halaman bonus */
    private function typeWeightsByDivision(?Division $div): array
    {
        $name = trim(strtolower($div->name ?? ''));

        // Technical Support Team
        if (str_contains($name,'technical') || str_contains($name,'support')) {
            return [
                'kuantitatif'=>0.5625,
                'kualitatif' =>0.0625,
                'response'   =>0.1875,
                'persentase' =>0.1875,
            ];
        }

        // Creatif Desain
        if (str_contains($name,'creatif') || str_contains($name,'creative') || str_contains($name,'desain')) {
            return [
                'kuantitatif'=>0.50,
                'kualitatif' =>0.25,
                'response'   =>0.125,
                'persentase' =>0.125,
            ];
        }

        // Default (mis. CSA — jika belum ditentukan)
        return ['kuantitatif'=>0.25,'kualitatif'=>0.25,'response'=>0.25,'persentase'=>0.25];
    }

    /**
     * Skor KPI Divisi berbobot tipe:
     * - Jika header.total_score tersedia → pakai langsung (sudah w*score dari seeder/controller).
     * - Jika tidak → rata-rata skor mentah per tipe × bobot tipe.
     * - KPI persentase level divisi → rata-rata skor × bobot tipe.
     * Hasil di skala 0..200 (sinkron halaman bonus).
     */
    private function getKpiDivisiScoreWeighted(User $user, int $bulan, int $tahun): ?float
    {
        $weights = $this->typeWeightsByDivision($user->division);

        $sum = 0.0; $any = false;

        // Kuantitatif
        $q = KpiDivisiKuantitatifRealization::where([
            'user_id'=>$user->id,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->get();
        if ($q->isNotEmpty()) {
            $any = true;
            if ($q->first()->getAttribute('total_score') !== null) {
                $sum += $q->sum('total_score');
            } else {
                $sum += $weights['kuantitatif'] * ((float)($q->avg('score') ?? 0));
            }
        }

        // Kualitatif
        $k = KpiDivisiKualitatifRealization::where([
            'user_id'=>$user->id,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->get();
        if ($k->isNotEmpty()) {
            $any = true;
            if ($k->first()->getAttribute('total_score') !== null) {
                $sum += $k->sum('total_score');
            } else {
                $sum += $weights['kualitatif'] * ((float)($k->avg('score') ?? 0));
            }
        }

        // Response
        $r = KpiDivisiResponseRealization::where([
            'user_id'=>$user->id,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->get();
        if ($r->isNotEmpty()) {
            $any = true;
            if ($r->first()->getAttribute('total_score') !== null) {
                $sum += $r->sum('total_score');
            } else {
                $sum += $weights['response'] * ((float)($r->avg('score') ?? 0));
            }
        }

        // Persentase (level divisi)
        $p = KpiDivisiPersentaseRealization::where([
            'division_id'=>$user->division_id,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->get();
        if ($p->isNotEmpty()) {
            $any = true;
            if ($p->first()->getAttribute('total_score') !== null) {
                $sum += $p->sum('total_score');
            } else {
                $sum += $weights['persentase'] * ((float)($p->avg('score') ?? 0));
            }
        }

        return $any ? round($sum, 2) : null;
    }

    /** Skor peer (1..10 → 0..100), hanya nilai locked jika kolom status ada */
    private function getPeerScore(int $userId, int $bulan, int $tahun): ?float
    {
        $ids = PeerAssessment::where([
            'assessee_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun
        ])->when(
            Schema::hasColumn('peer_assessments','status'),
            fn($q)=>$q->where('status','locked')
        )->pluck('id');

        if ($ids->isEmpty()) return null;

        $scores = PeerAssessmentItem::whereIn('assessment_id',$ids)->pluck('score');
        if ($scores->isEmpty()) return null;

        return round(((float)$scores->avg()) * 10.0, 2);
    }

    /** Normalisasi bobot global hanya pada komponen yang ada nilainya */
    private function renormalizeWeights(array $values, array $weights): array
    {
        $sum = 0.0; $act = [];
        foreach ($values as $k=>$v) {
            if ($v !== null) { $act[$k] = $weights[$k]; $sum += $weights[$k]; }
            else            { $act[$k] = 0.0; }
        }
        if ($sum <= 0) return ['umum'=>1/3,'divisi'=>1/3,'peer'=>1/3];
        foreach ($act as $k=>$w) $act[$k] = $w / $sum;
        return $act;
    }
}
