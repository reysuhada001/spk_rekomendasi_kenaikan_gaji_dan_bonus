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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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

        // Dropdown divisi: default ke divisi user jika kosong
        $divisionId = (int) $request->input('division_id', 0);
        if ($divisionId === 0 && $me->division_id) $divisionId = (int) $me->division_id;

        // 3 kartu leaderboard
        $topGlobal   = $this->buildTopGlobal($bulan, $tahun, 5);                          // AHP global + renormalisasi
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

    /* ================= Helpers (periode & bobot) ================= */

    private function resolvePeriode(Request $r): array
    {
        $now = Carbon::now();
        return [(int)$r->input('bulan', $now->month), (int)$r->input('tahun', $now->year)];
    }

    /** Ambil bobot AHP global (mendukung kolom w_* maupun non-w_*), lalu normalisasi. */
    private function getGlobalWeights(): array
    {
        $w = AhpGlobalWeight::latest()->first();
        if (!$w) return [1/3,1/3,1/3];

        $ku = (float)($w->w_kpi_umum   ?? $w->kpi_umum   ?? 0);
        $kd = (float)($w->w_kpi_divisi ?? $w->kpi_divisi ?? 0);
        $kp = (float)($w->w_peer       ?? $w->peer       ?? 0);
        $sum = $ku + $kd + $kp;

        if ($sum <= 0) return [1/3,1/3,1/3];
        return [$ku/$sum, $kd/$sum, $kp/$sum];
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

    /* ================= Primitive scorers ================= */

    /** KPI Umum (0..200) */
    private function kpiUmum(int $userId, int $bulan, int $tahun): ?float
    {
        $row = KpiUmumRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->first();
        return $row ? round((float)$row->total_score, 2) : null;
    }

    /** Bobot tipe per divisi (jumlah=1) — sama seperti di LeaderboardMonthlyController */
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

        // Default (mis. CSA)
        return ['kuantitatif'=>0.25,'kualitatif'=>0.25,'response'=>0.25,'persentase'=>0.25];
    }

    /**
     * Skor KPI Divisi untuk GLOBAL & PER-DIVISI KARYAWAN (0..200):
     * mengikuti LeaderboardMonthlyController (komponen persentase = rata2 skor × bobot tipe).
     */
    private function getKpiDivisiScoreWeightedForGlobal(User $user, int $bulan, int $tahun): ?float
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

        // Persentase (level divisi) — versi GLOBAL
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

    /**
     * Skor KPI Divisi untuk RANK DIVISI (0..200):
     * mengikuti LeaderboardDivisionKpiController — komponen persentase via join:
     *   SUM(kpi_divisi.bobot * kpi_divisi_persentase_realizations.score)
     */
    private function getKpiDivisiScoreForUserExact(int $userId, int $divisionId, int $bulan, int $tahun, ?Division $division = null): ?float
    {
        $sum = 0.0; $any = false;

        // Kuantitatif
        $q = KpiDivisiKuantitatifRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->sum('total_score');
        if ($q > 0) { $sum += (float)$q; $any = true; }

        // Kualitatif
        $k = KpiDivisiKualitatifRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->sum('total_score');
        if ($k > 0) { $sum += (float)$k; $any = true; }

        // Response
        $r = KpiDivisiResponseRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->sum('total_score');
        if ($r > 0) { $sum += (float)$r; $any = true; }

        // Persentase (level divisi) → join bobot KPI
        $persenWeighted = KpiDivisiPersentaseRealization::query()
            ->join('kpi_divisi','kpi_divisi.id','=','kpi_divisi_persentase_realizations.kpi_divisi_id')
            ->where('kpi_divisi_persentase_realizations.division_id', $divisionId)
            ->where('kpi_divisi_persentase_realizations.bulan', $bulan)
            ->where('kpi_divisi_persentase_realizations.tahun', $tahun)
            ->where('kpi_divisi_persentase_realizations.status', 'approved')
            ->sum(DB::raw('kpi_divisi.bobot * kpi_divisi_persentase_realizations.score'));

        if ($persenWeighted > 0) { $sum += (float)$persenWeighted; $any = true; }

        return $any ? round($sum, 2) : null;
    }

    /** Peer (1..10 → 0..100), hanya locked jika kolom status tersedia. */
    private function peerScore(int $userId, int $bulan, int $tahun): ?float
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

    /** Skor bulanan murni: (umum/2, divisi/2, peer) lalu rata-rata komponen yang ada. */
    private function monthlyRaw(?float $sUmum, ?float $sDiv, ?float $sPeer): float
    {
        $parts=[];
        if ($sUmum!==null) $parts[] = max(0.0, min(100.0, $sUmum/2.0));
        if ($sDiv !==null) $parts[] = max(0.0, min(100.0, $sDiv /2.0));
        if ($sPeer!==null) $parts[] = max(0.0, min(100.0, $sPeer));
        return empty($parts) ? 0.0 : round(array_sum($parts)/count($parts), 4);
    }

    /* ================= Builders for cards ================= */

    /**
     * Top N Global — mengikuti LeaderboardMonthlyController
     * (Ariyani akan #1 jika di leaderboard bulanan juga #1).
     */
    private function buildTopGlobal(int $bulan, int $tahun, int $limit=5): array
    {
        [$wUmum, $wDiv, $wPeer] = $this->getGlobalWeights();
        $rows = [];

        $karyawan = User::with('division')->where('role','karyawan')->get();
        foreach ($karyawan as $u) {
            $ku = $this->kpiUmum($u->id, $bulan, $tahun);                                  // 0..200 | null
            $kd = $this->getKpiDivisiScoreWeightedForGlobal($u, $bulan, $tahun);           // 0..200 | null
            $kp = $this->peerScore($u->id, $bulan, $tahun);                                 // 0..100 | null

            $weights = $this->renormalizeWeights(
                ['umum'=>$ku,'divisi'=>$kd,'peer'=>$kp],
                ['umum'=>$wUmum,'divisi'=>$wDiv,'peer'=>$wPeer]
            );

            $final = 0.0;
            if ($ku !== null) $final += $weights['umum']   * $ku;
            if ($kd !== null) $final += $weights['divisi'] * $kd;
            if ($kp !== null) $final += $weights['peer']   * $kp;

            $rows[] = [
                'name'     => $u->full_name,
                'division' => $u->division?->name ?? '-',
                'score'    => round($final, 4),
            ];
        }

        usort($rows, fn($a,$b)=>$b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as $i=>&$r) $r['rank'] = $i+1;
        return $rows;
    }

    /** Top N karyawan di satu divisi: skor bulanan murni (umum/2, divisi/2, peer). */
    private function buildTopWithinDivision(int $bulan, int $tahun, int $divisionId, int $limit=5): array
    {
        $rows = [];
        $karyawan = User::with('division')
            ->where('role','karyawan')->where('division_id',$divisionId)->get();

        foreach ($karyawan as $u) {
            $ku = $this->kpiUmum($u->id, $bulan, $tahun);
            $kd = $this->getKpiDivisiScoreWeightedForGlobal($u, $bulan, $tahun); // tetap pakai versi GLOBAL agar konsisten dengan halaman karyawan-per-divisi yang sudah benar
            $kp = $this->peerScore($u->id, $bulan, $tahun);

            $final = $this->monthlyRaw($ku, $kd, $kp);

            $rows[] = [
                'name'     => $u->full_name,
                'division' => $u->division?->name ?? '-',
                'score'    => round($final, 2),
            ];
        }

        usort($rows, fn($a,$b)=>$b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as $i=>&$r) $r['rank'] = $i+1;
        return $rows;
    }

    /**
     * Top N Divisi (KPI Divisi):
     * rata-rata (KPI Divisi per karyawan / 2) → skala 0..100,
     * dan PERHITUNGAN per-karyawan mengikuti LeaderboardDivisionKpiController (join bobot KPI).
     */
    private function buildTopDivisionsKpi(int $bulan, int $tahun, int $limit=5): array
    {
        $rows = [];
        $divs = Division::orderBy('name')->get();

        foreach ($divs as $d) {
            $userIds = User::where('role','karyawan')->where('division_id',$d->id)->pluck('id');
            if ($userIds->isEmpty()) continue;

            $vals = [];
            foreach ($userIds as $uid) {
                $s200 = $this->getKpiDivisiScoreForUserExact((int)$uid, (int)$d->id, $bulan, $tahun, $d); // 0..200 | null
                if ($s200 !== null) $vals[] = max(0.0, min(100.0, $s200 / 2.0));
            }
            if (empty($vals)) continue;

            $avg = array_sum($vals) / count($vals);
            $rows[] = [
                'division' => $d->name,
                'n_users'  => count($vals),
                'score'    => round($avg, 2),
            ];
        }

        usort($rows, fn($a,$b)=>$b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as $i=>&$r) $r['rank'] = $i+1;
        return $rows;
    }
}
