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
use Carbon\Carbon;

class DashboardController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    public function index()
    {
        return match (Auth::user()->role) {
            'owner'    => redirect()->route('dashboard.owner'),
            'hr'       => redirect()->route('dashboard.hr'),
            'leader'   => redirect()->route('dashboard.leader'),
            'karyawan' => redirect()->route('dashboard.karyawan'),
            default    => abort(403, 'Role tidak dikenali'),
        };
    }

    public function owner(Request $r)    { return $this->render($r, 'Dashboard Owner'); }
    public function hr(Request $r)       { return $this->render($r, 'Dashboard HR'); }
    public function leader(Request $r)   { return $this->render($r, 'Dashboard Leader'); }
    public function karyawan(Request $r) { return $this->render($r, 'Dashboard Karyawan'); }

    private function render(Request $request, string $pageTitle)
    {
        $me = Auth::user();
        [$bulan, $tahun] = $this->resolvePeriode($request);

        // Dropdown divisi SELALU tampil; jika kosong, default ke divisi user (jika ada)
        $divisionId = (int) $request->input('division_id', 0);
        if ($divisionId === 0 && $me->division_id) {
            $divisionId = (int) $me->division_id;
        }

        // Siapkan data untuk 3 leaderboard
        $topGlobal   = $this->buildTopGlobal($bulan, $tahun, 5);
        $topInDivisi = $divisionId ? $this->buildTopWithinDivision($bulan, $tahun, $divisionId, 5) : [];
        $topDivisi   = $this->buildTopDivisionsKpi($bulan, $tahun, 5);

        return view($this->viewForRole($me->role), [
            'me'         => $me,
            'pageTitle'  => $pageTitle,
            'bulan'      => $bulan,
            'tahun'      => $tahun,
            'bulanList'  => $this->bulanList,
            'divisions'  => Division::orderBy('name')->get(),
            'divisionId' => $divisionId,
            'topGlobal'  => $topGlobal,
            'topInDivisi'=> $topInDivisi,
            'topDivisi'  => $topDivisi,
        ]);
    }

    private function viewForRole(string $role): string
    {
        return match ($role) {
            'owner'    => 'dashboard.owner',
            'hr'       => 'dashboard.hr',
            'leader'   => 'dashboard.leader',
            'karyawan' => 'dashboard.karyawan',
            default    => 'dashboard.hr',
        };
    }

    /* ============== Helpers ============== */

    private function resolvePeriode(Request $r): array
    {
        $now = Carbon::now();
        return [(int)$r->input('bulan', $now->month), (int)$r->input('tahun', $now->year)];
    }

    /** Top N Global (gabungan KPI Umum, KPI Divisi, Peer) */
    private function buildTopGlobal(int $bulan, int $tahun, int $limit=5): array
    {
        [$wUmum, $wDiv, $wPeer] = $this->getGlobalWeights();
        $rows = [];

        $karyawan = User::with('division')->where('role','karyawan')->get();
        foreach ($karyawan as $u) {
            $sUmum = $this->getKpiUmumScore($u->id, $bulan, $tahun) ?? 0.0;
            $sDiv  = $this->getKpiDivisiScore($u->id, (int)$u->division_id, $bulan, $tahun) ?? 0.0;
            $sPeer = $this->getPeerScore($u->id, $bulan, $tahun) ?? 0.0;
            $final = $wUmum*$sUmum + $wDiv*$sDiv + $wPeer*$sPeer;

            $rows[] = [
                'name'     => $u->full_name,
                'division' => $u->division?->name ?? '-',
                'score'    => round($final, 2),
            ];
        }

        usort($rows, fn($a,$b)=>$b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as $i => &$r) $r['rank'] = $i + 1;
        return $rows;
    }

    /** Top N Karyawan dalam satu divisi */
    private function buildTopWithinDivision(int $bulan, int $tahun, int $divisionId, int $limit=5): array
    {
        [$wUmum, $wDiv, $wPeer] = $this->getGlobalWeights();
        $rows = [];

        $karyawan = User::with('division')
            ->where('role','karyawan')->where('division_id',$divisionId)->get();

        foreach ($karyawan as $u) {
            $sUmum = $this->getKpiUmumScore($u->id, $bulan, $tahun) ?? 0.0;
            $sDiv  = $this->getKpiDivisiScore($u->id, $divisionId, $bulan, $tahun) ?? 0.0;
            $sPeer = $this->getPeerScore($u->id, $bulan, $tahun) ?? 0.0;
            $final = $wUmum*$sUmum + $wDiv*$sDiv + $wPeer*$sPeer;

            $rows[] = [
                'name'     => $u->full_name,
                'division' => $u->division?->name ?? '-',
                'score'    => round($final, 2),
            ];
        }

        usort($rows, fn($a,$b)=>$b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as $i => &$r) $r['rank'] = $i + 1;
        return $rows;
    }

    /** Top N Divisi berdasarkan rata-rata KPI Divisi karyawannya */
    private function buildTopDivisionsKpi(int $bulan, int $tahun, int $limit=5): array
    {
        $rows = [];
        $divs = Division::orderBy('name')->get();

        foreach ($divs as $d) {
            $userIds = User::where('role','karyawan')->where('division_id',$d->id)->pluck('id');
            if ($userIds->isEmpty()) continue;

            $scores = [];
            foreach ($userIds as $uid) {
                $s = $this->getKpiDivisiScore($uid, $d->id, $bulan, $tahun);
                if ($s !== null) $scores[] = $s;
            }
            if (empty($scores)) continue;

            $avg = array_sum($scores)/count($scores);
            $rows[] = [
                'division' => $d->name,
                'n_users'  => count($scores),
                'score'    => round($avg, 2),
            ];
        }

        usort($rows, fn($a,$b)=>$b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as $i => &$r) $r['rank'] = $i + 1;
        return $rows;
    }

    /* --------- Primitive Scorers --------- */

    private function getGlobalWeights(): array
    {
        $w = AhpGlobalWeight::latest()->first();
        if (!$w) return [1/3,1/3,1/3];

        $ku=(float)($w->kpi_umum ?? 0);
        $kd=(float)($w->kpi_divisi ?? 0);
        $kp=(float)($w->peer ?? 0);
        $sum=$ku+$kd+$kp;
        if ($sum<=0) return [1/3,1/3,1/3];
        return [$ku/$sum,$kd/$sum,$kp/$sum];
    }

    private function getKpiUmumScore(int $userId,int $bulan,int $tahun): ?float
    {
        $r = KpiUmumRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->first();
        return $r ? (float)$r->total_score : null;
    }

    private function getKpiDivisiScore(int $userId,int $divisionId,int $bulan,int $tahun): ?float
    {
        $scores=[];

        $rq=KpiDivisiKuantitatifRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->first();
        if($rq && $rq->total_score!==null)$scores[]=(float)$rq->total_score;

        $rk=KpiDivisiKualitatifRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->first();
        if($rk && $rk->total_score!==null)$scores[]=(float)$rk->total_score;

        $rr=KpiDivisiResponseRealization::where('user_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->first();
        if($rr && $rr->total_score!==null)$scores[]=(float)$rr->total_score;

        $rp=KpiDivisiPersentaseRealization::where('division_id',$divisionId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->pluck('score');
        if($rp && $rp->count()>0)$scores[]=(float)round($rp->avg(),2);

        if(empty($scores))return null;
        return array_sum($scores)/count($scores);
    }

    private function getPeerScore(int $userId,int $bulan,int $tahun): ?float
    {
        $ids=PeerAssessment::where('assessee_id',$userId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->when(Schema::hasColumn('peer_assessments','status'), fn($q)=>$q->where('status','locked'))
            ->pluck('id');
        if($ids->isEmpty())return null;

        $scores=PeerAssessmentItem::whereIn('assessment_id',$ids)->pluck('score');
        if($scores->isEmpty())return null;

        $avg10=(float)$scores->avg();
        return round($avg10*10.0,2);
    }
}
