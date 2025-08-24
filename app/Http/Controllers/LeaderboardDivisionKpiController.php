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
use Illuminate\Support\Facades\DB;
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
            $userIds = User::where('role','karyawan')->where('division_id', $div->id)->pluck('id');
            if ($userIds->isEmpty()) continue;

            $scores = [];
            foreach ($userIds as $uid) {
                $s = $this->getKpiDivisiScoreForUser((int)$uid, (int)$div->id, $bulan, $tahun, $div);
                if ($s !== null) $scores[] = $s; // 0..200
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
     * PENJUMLAHAN kontribusi berbobot per tipe (0..200).
     * - Kuantitatif/Kualitatif/Response: pakai header.total_score (w*score).
     *   Jika tidak ada, fallback: rata-rata skor mentah × bobot tipe divisi.
     * - Persentase (level divisi): SUM(bobot_kpi * score) pada bulan/tahun/divisi tsb;
     *   nilainya sama untuk semua karyawan di divisi pada periode tsb.
     */
    private function getKpiDivisiScoreForUser(int $userId, int $divisionId, int $bulan, int $tahun, ?Division $division = null): ?float
    {
        $sum = 0.0;
        $any = false;

        // Kuantitatif
        $q = KpiDivisiKuantitatifRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->get();

        if ($q->isNotEmpty()) {
            $any = true;
            if ($q->first()->getAttribute('total_score') !== null) {
                $sum += $q->sum('total_score');
            } else {
                $sum += $this->typeWeightsByDivision($division)['kuantitatif'] * ((float)($q->avg('score') ?? 0));
            }
        }

        // Kualitatif
        $k = KpiDivisiKualitatifRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->get();

        if ($k->isNotEmpty()) {
            $any = true;
            if ($k->first()->getAttribute('total_score') !== null) {
                $sum += $k->sum('total_score');
            } else {
                $sum += $this->typeWeightsByDivision($division)['kualitatif'] * ((float)($k->avg('score') ?? 0));
            }
        }

        // Response
        $r = KpiDivisiResponseRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->get();

        if ($r->isNotEmpty()) {
            $any = true;
            if ($r->first()->getAttribute('total_score') !== null) {
                $sum += $r->sum('total_score');
            } else {
                $sum += $this->typeWeightsByDivision($division)['response'] * ((float)($r->avg('score') ?? 0));
            }
        }

        // Persentase (level divisi) → SUM(bobot_kpi * score)
        $persenWeighted = KpiDivisiPersentaseRealization::query()
            ->join('kpi_divisi','kpi_divisi.id','=','kpi_divisi_persentase_realizations.kpi_divisi_id')
            ->where('kpi_divisi_persentase_realizations.division_id', $divisionId)
            ->where('kpi_divisi_persentase_realizations.bulan', $bulan)
            ->where('kpi_divisi_persentase_realizations.tahun', $tahun)
            ->where('kpi_divisi_persentase_realizations.status', 'approved')
            ->sum(DB::raw('kpi_divisi.bobot * kpi_divisi_persentase_realizations.score'));

        if ($persenWeighted > 0) { $sum += (float)$persenWeighted; $any = true; }

        return $any ? round($sum, 2) : null; // 0..200
    }

    /** Bobot tipe per divisi (jumlah = 1) – fallback jika header.total_score belum tersedia */
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

        // Default (mis. CSA bila belum diatur spesifik)
        return ['kuantitatif'=>0.25,'kualitatif'=>0.25,'response'=>0.25,'persentase'=>0.25];
    }
}
