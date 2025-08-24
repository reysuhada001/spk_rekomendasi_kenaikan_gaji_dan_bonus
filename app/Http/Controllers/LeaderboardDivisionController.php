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
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class LeaderboardDivisionController extends Controller
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

        // Division filter behavior by role
        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;
        if (in_array($me->role, ['leader','karyawan'], true)) {
            $division_id = $me->division_id;
        }

        $divisions = Division::orderBy('name')->get();
        $needDivision = in_array($me->role, ['owner','hr'], true) && empty($division_id);

        // jika periode belum dipilih / admin belum pilih divisi → kosong
        if (is_null($bulan) || is_null($tahun) || $needDivision) {
            $empty = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path'=>$request->url(), 'query'=>$request->query()
            ]);
            return view('leaderboard-divisi.index', [
                'me'=>$me, 'users'=>$empty, 'rows'=>[],
                'bulan'=>$bulan, 'tahun'=>$tahun, 'division_id'=>$division_id,
                'divisions'=>$divisions, 'bulanList'=>$this->bulanList, 'perPage'=>$perPage,
                'needDivision'=>$needDivision,
            ]);
        }

        // Ambil karyawan pada divisi terpilih saja
        $karyawan = User::with('division')
            ->where('role', 'karyawan')
            ->where('division_id', $division_id)
            ->orderBy('full_name')
            ->get();

        // (Bobot global tidak dipakai untuk ranking—tetap disediakan jika sewaktu-waktu mau ditampilkan)
        [$wUmum, $wDiv, $wPeer] = $this->getGlobalWeights();

        $rows = [];
        foreach ($karyawan as $u) {
            $sUmum = $this->getKpiUmumScore($u->id, $bulan, $tahun);                         // 0..200
            $sDiv  = $this->getKpiDivisiScoreWeighted($u->id, (int)$u->division_id, $bulan, $tahun); // 0..200
            $sPeer = $this->getPeerScore($u->id, $bulan, $tahun);                            // 0..100

            // Skor bulanan murni (tanpa bobot global) untuk ranking
            $finalRaw = $this->composeMonthlyRawScore($sUmum, $sDiv, $sPeer); // 0..100

            $rows[] = [
                'user_id'  => $u->id,
                'name'     => $u->full_name,
                'division' => $u->division?->name ?? '-',
                'final'    => round($finalRaw, 4),

                // opsional: jika ingin ditampilkan di view
                's_umum' => $sUmum, 's_div' => $sDiv, 's_peer' => $sPeer,
                'w_umum' => $wUmum, 'w_div' => $wDiv, 'w_peer' => $wPeer,
            ];
        }

        // Urutkan desc & beri ranking (tie-aware)
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

        // Pagination manual
        $currentPage = max(1, (int)$request->input('page', 1));
        $slice = array_slice($rows, ($currentPage-1)*$perPage, $perPage);
        $paginator = new LengthAwarePaginator($slice, count($rows), $perPage, $currentPage, [
            'path'=>$request->url(),
            'query'=>$request->query(),
        ]);

        return view('leaderboard-divisi.index', [
            'me'=>$me,
            'users'=>$paginator,
            'rows'=>collect($slice)->keyBy('user_id'),
            'bulan'=>$bulan,
            'tahun'=>$tahun,
            'division_id'=>$division_id,
            'divisions'=>$divisions,
            'bulanList'=>$this->bulanList,
            'perPage'=>$perPage,
            'needDivision'=>false,
        ]);
    }

    /* ===================== HELPERS ===================== */

    /** Bobot global (tidak dipakai untuk ranking, hanya opsional untuk ditampilkan) */
    private function getGlobalWeights(): array
    {
        $w = AhpGlobalWeight::latest()->first();
        if (!$w) return [1/3, 1/3, 1/3];

        $ku = (float)($w->kpi_umum   ?? 0);
        $kd = (float)($w->kpi_divisi ?? 0);
        $kp = (float)($w->peer       ?? 0);
        $sum = $ku + $kd + $kp;
        if ($sum <= 0) return [1/3, 1/3, 1/3];

        return [$ku/$sum, $kd/$sum, $kp/$sum];
    }

    /** Skor KPI Umum (approved) — skala 0..200 */
    private function getKpiUmumScore(int $userId, int $bulan, int $tahun): ?float
    {
        $r = KpiUmumRealization::where('user_id', $userId)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->where('status','approved')
            ->first();

        return $r? (float)$r->total_score : null;
    }

    /**
     * Skor KPI Divisi ter-bobot (0..200):
     *   Kuantitatif/Kualitatif/Response  → header.total_score (w*score)
     *   Persentase (level divisi)        → SUM(bobot_kpi * score) via join ke kpi_divisi
     */
    private function getKpiDivisiScoreWeighted(int $userId, int $divisionId, int $bulan, int $tahun): ?float
    {
        $sumWeighted = 0.0;
        $hasAny = false;

        $rq = KpiDivisiKuantitatifRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->value('total_score');
        if (!is_null($rq)) { $sumWeighted += (float)$rq; $hasAny = true; }

        $rk = KpiDivisiKualitatifRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->value('total_score');
        if (!is_null($rk)) { $sumWeighted += (float)$rk; $hasAny = true; }

        $rr = KpiDivisiResponseRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->value('total_score');
        if (!is_null($rr)) { $sumWeighted += (float)$rr; $hasAny = true; }

        // Persentase ⇒ SUM(bobot_kpi * score) pada periode & divisi yang sama
        $persenSum = KpiDivisiPersentaseRealization::query()
            ->join('kpi_divisi','kpi_divisi.id','=','kpi_divisi_persentase_realizations.kpi_divisi_id')
            ->where('kpi_divisi_persentase_realizations.division_id', $divisionId)
            ->where('kpi_divisi_persentase_realizations.bulan', $bulan)
            ->where('kpi_divisi_persentase_realizations.tahun', $tahun)
            ->where('kpi_divisi_persentase_realizations.status', 'approved')
            ->sum(DB::raw('kpi_divisi.bobot * kpi_divisi_persentase_realizations.score'));

        if ($persenSum > 0) { $sumWeighted += (float)$persenSum; $hasAny = true; }

        return $hasAny ? round($sumWeighted, 2) : null;
    }

    /** Skor peer (1..10 → 0..100), hanya data locked bila kolom status tersedia */
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
        $avg100 = $avg10 * 10.0;        // 0..100
        return round($avg100, 2);
    }

    /**
     * Skor bulanan murni untuk ranking:
     * - KPI Umum & KPI Divisi (0..200) → ÷2 menjadi 0..100
     * - Peer (0..100) tetap
     * - Dirata-ratakan hanya komponen yang tersedia.
     */
    private function composeMonthlyRawScore(?float $sUmum, ?float $sDiv, ?float $sPeer): float
    {
        $parts = [];
        if ($sUmum !== null) $parts[] = max(0.0, min(100.0, $sUmum / 2.0));
        if ($sDiv  !== null) $parts[] = max(0.0, min(100.0, $sDiv  / 2.0));
        if ($sPeer !== null) $parts[] = max(0.0, min(100.0, $sPeer));

        if (empty($parts)) return 0.0;
        return round(array_sum($parts) / count($parts), 4);
    }
}
