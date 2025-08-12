<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;

use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiPersentaseRealization;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class LeaderboardDivisionKpiController extends Controller
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

        $bulan = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun = $request->filled('tahun') ? (int)$request->tahun : null;

        $divisions = Division::orderBy('name')->get();

        // Belum pilih periode → kosong
        if (is_null($bulan) || is_null($tahun)) {
            $empty = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path'=>$request->url(), 'query'=>$request->query()
            ]);
            return view('leaderboard-divisi-kpi.index', [
                'me'=>$me, 'rows'=>$empty, 'bulan'=>$bulan, 'tahun'=>$tahun,
                'bulanList'=>$this->bulanList, 'perPage'=>$perPage,
            ]);
        }

        // Hitung skor KPI Divisi per divisi: rata-rata skor KPI Divisi karyawan pada periode tsb
        $rows = [];
        foreach ($divisions as $div) {
            // Ambil karyawan di divisi ini
            $users = User::where('role','karyawan')->where('division_id', $div->id)->pluck('id');
            if ($users->isEmpty()) continue;

            $scores = [];
            foreach ($users as $uid) {
                $s = $this->getKpiDivisiScoreForUser($uid, $div->id, $bulan, $tahun);
                if ($s !== null) $scores[] = $s;
            }

            if (empty($scores)) continue; // tak ada data valid di periode ini

            $avg = array_sum($scores) / count($scores);

            $rows[] = [
                'division_id' => $div->id,
                'division'    => $div->name,
                'avg_score'   => round($avg, 4),
                'n_users'     => count($scores),
            ];
        }

        // Urutkan desc & beri ranking (tie aware)
        usort($rows, function($a,$b){
            if (abs($b['avg_score'] - $a['avg_score']) < 0.0001) return 0;
            return $b['avg_score'] <=> $a['avg_score'];
        });
        $rank = 0; $prev = null;
        foreach ($rows as &$r) {
            if ($prev === null || abs($r['avg_score'] - $prev) > 0.0001) {
                $rank++;
                $prev = $r['avg_score'];
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

        return view('leaderboard-divisi-kpi.index', [
            'me'=>$me,
            'rows'=>$paginator,
            'bulan'=>$bulan,
            'tahun'=>$tahun,
            'bulanList'=>$this->bulanList,
            'perPage'=>$perPage,
        ]);
    }

    /**
     * Skor KPI Divisi per karyawan pada periode:
     * gabungan (rata-rata) dari skor tiap tipe (yang tersedia & approved).
     */
    private function getKpiDivisiScoreForUser(int $userId, int $divisionId, int $bulan, int $tahun): ?float
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

        // Persentase: per-KPI Divisi (divisional) → ambil rata-rata skor KPI persentase approved
        $rp = KpiDivisiPersentaseRealization::where('division_id',$divisionId)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('status','approved')->pluck('score');
        if ($rp && $rp->count() > 0) $scores[] = (float) round($rp->avg(), 2);

        if (empty($scores)) return null;
        return array_sum($scores) / count($scores);
    }
}
