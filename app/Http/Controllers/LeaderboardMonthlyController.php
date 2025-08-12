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
        // semua role bisa melihat
        $this->middleware(['auth','role:owner,hr,leader,karyawan']);
    }

    public function index(Request $request)
    {
        $me      = Auth::user();
        $perPage = (int)$request->input('per_page', 10);
        $bulan   = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun   = $request->filled('tahun') ? (int)$request->tahun : null;

        // Jika periode belum dipilih, tampilkan kosong tapi UI tetap rapi
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

        // Ambil semua karyawan (tanpa filter divisi—leaderboard global)
        $karyawan = User::with('division')
            ->where('role', 'karyawan')
            ->orderBy('full_name')
            ->get();

        // Bobot AHP Global (fallback 1/3)
        [$wUmum, $wDiv, $wPeer] = $this->getGlobalWeights();

        // Hitung skor akhir per karyawan
        $rows = [];
        foreach ($karyawan as $u) {
            $sUmum = $this->getKpiUmumScore($u->id, $bulan, $tahun) ?? 0.0;
            $sDiv  = $this->getKpiDivisiScore($u->id, (int)$u->division_id, $bulan, $tahun) ?? 0.0;
            $sPeer = $this->getPeerScore($u->id, $bulan, $tahun) ?? 0.0;

            $final = $wUmum*$sUmum + $wDiv*$sDiv + $wPeer*$sPeer;

            $rows[] = [
                'user_id'  => $u->id,
                'name'     => $u->full_name,
                'division' => $u->division?->name ?? '-',
                'final'    => round($final, 4),
            ];
        }

        // Urutkan desc & beri ranking (tie-friendly)
        usort($rows, function($a,$b){
            if (abs($b['final'] - $a['final']) < 0.0001) return 0;
            return $b['final'] <=> $a['final'];
        });

        $rank = 0; $prev = null;
        foreach ($rows as &$r) {
            if ($prev === null || abs($r['final'] - $prev) > 0.0001) {
                $rank++;
                $prev = $r['final'];
            }
            $r['rank'] = $rank;
        }
        unset($r);

        // Pagination manual karena data sudah jadi array
        $currentPage = max(1, (int)$request->input('page', 1));
        $slice = array_slice($rows, ($currentPage-1)*$perPage, $perPage);
        $paginator = new LengthAwarePaginator($slice, count($rows), $perPage, $currentPage, [
            'path'  => $request->url(),
            'query' => $request->query(),
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

    /** Ambil bobot AHP global. Jika belum ada, 1/3 masing-masing. */
    private function getGlobalWeights(): array
    {
        $w = AhpGlobalWeight::latest()->first();
        if (!$w) return [1/3, 1/3, 1/3];

        $ku = (float)($w->kpi_umum  ?? 0);
        $kd = (float)($w->kpi_divisi?? 0);
        $kp = (float)($w->peer      ?? 0);
        $sum = $ku + $kd + $kp;
        if ($sum <= 0) return [1/3, 1/3, 1/3];

        return [$ku/$sum, $kd/$sum, $kp/$sum];
    }

    /** Skor KPI Umum (approved) untuk user & periode. */
    private function getKpiUmumScore(int $userId, int $bulan, int $tahun): ?float
    {
        $r = KpiUmumRealization::where('user_id', $userId)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->where('status','approved')
            ->first();

        return $r? (float)$r->total_score : null;
    }

    /** Skor KPI Divisi = rata-rata dari tipe yang tersedia (approved). */
    private function getKpiDivisiScore(int $userId, int $divisionId, int $bulan, int $tahun): ?float
    {
        $scores = [];

        $rq = KpiDivisiKuantitatifRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->first();
        if ($rq && $rq->total_score !== null) $scores[] = (float)$rq->total_score;

        $rk = KpiDivisiKualitatifRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->first();
        if ($rk && $rk->total_score !== null) $scores[] = (float)$rk->total_score;

        $rr = KpiDivisiResponseRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->first();
        if ($rr && $rr->total_score !== null) $scores[] = (float)$rr->total_score;

        // Persentase: per KPI Divisi (bukan per user) — ambil rata-rata skor KPI persentase yang approved
        $rp = KpiDivisiPersentaseRealization::where('division_id',$divisionId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->pluck('score');
        if ($rp && $rp->count() > 0) $scores[] = (float) round($rp->avg(), 2);

        if (empty($scores)) return null;
        return array_sum($scores) / count($scores);
    }

    /** Skor penilaian rekan (1..10 → 0..100), hanya data locked bila kolom status tersedia. */
    private function getPeerScore(int $userId, int $bulan, int $tahun): ?float
    {
        $assessmentIds = PeerAssessment::where('assessee_id', $userId)
            ->where('bulan', $bulan)->where('tahun', $tahun)
            ->when(Schema::hasColumn('peer_assessments','status'), fn($q)=>$q->where('status','locked'))
            ->pluck('id');

        if ($assessmentIds->isEmpty()) return null;

        $scores = PeerAssessmentItem::whereIn('assessment_id', $assessmentIds)->pluck('score');
        if ($scores->isEmpty()) return null;

        $avg10  = (float)$scores->avg(); // 1..10
        $avg100 = $avg10 * 10.0;        // → 0..100
        return round($avg100, 2);
    }
}
