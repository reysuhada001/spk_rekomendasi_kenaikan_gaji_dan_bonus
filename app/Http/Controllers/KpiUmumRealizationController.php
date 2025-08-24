<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\KpiUmum;
use App\Models\KpiUmumRealization;
use App\Models\KpiUmumRealizationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KpiUmumRealizationController extends Controller
{
    /** Batas atas skor saat >= target (sesuai skripsi) */
    private const CAP_MAX = 200.0;

    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    /* ====================== Helpers Fuzzy ====================== */

    /** Triangular membership μ(x; a,b,c) */
    private function tri(float $x, float $a, float $b, float $c): float
    {
        if ($x <= $a || $x >= $c) return 0.0;
        if ($x == $b) return 1.0;
        if ($x < $b) return ($x - $a) / max(1e-9, $b - $a);
        return ($c - $x) / max(1e-9, $c - $b);
    }

    /** Fuzzy (L/M/H) untuk kuantitatif, kualitatif – anchors 50/80/95 */
    private function fuzzyScoreGeneral(float $ratio): float
    {
        // L : 0 .. 0.3 .. 0.6
        $muL = $this->tri($ratio, 0.0, 0.3, 0.6);
        // M : 0.4 .. 0.7 .. 1.0
        $muM = $this->tri($ratio, 0.4, 0.7, 1.0);
        // H : 0.6 .. 0.9 .. 1.0
        $muH = $this->tri($ratio, 0.6, 0.9, 1.0);

        $num = $muL * 50 + $muM * 80 + $muH * 95;
        $den = $muL + $muM + $muH;
        return $den > 0 ? ($num / $den) : 50.0;
    }

    /** Fuzzy (L/M/H) *khusus response* saat lambat (x = y < 1) — rumus skripsi 0.1–0.4–0.8; H=1 bila x>0.9 */
    private function fuzzyScoreResponse(float $x): float
    {
        $muL = 0.0; $muM = 0.0; $muH = 0.0;

        // Low: 1 pada x<=0.3 lalu turun linier ke 0 di 0.6
        if     ($x <= 0.3) $muL = 1.0;
        elseif ($x <= 0.6) $muL = (0.6 - $x) / (0.6 - 0.3 + 1e-9);

        // Mid: segitiga 0.1–0.4–0.8
        if      ($x > 0.1 && $x <= 0.4) $muM = ($x - 0.1) / (0.4 - 0.1 + 1e-9);
        elseif  ($x > 0.4 && $x <= 0.8) $muM = (0.8 - $x) / (0.8 - 0.4 + 1e-9);
        $muM = max(0.0, min(1.0, $muM));

        // High: naik 0.6–0.9 lalu tetap 1 untuk x>0.9
        if      ($x > 0.6 && $x <= 0.9) $muH = ($x - 0.6) / (0.9 - 0.6 + 1e-9);
        elseif  ($x > 0.9)              $muH = 1.0;

        $den = $muL + $muM + $muH;
        if ($den <= 0) return 50.0;
        return ($muL*50 + $muM*80 + $muH*95) / $den;
    }

    /** Fuzzy (L/M/H) khusus persentase – anchors 60/85/98 */
    private function fuzzyScorePercent(float $ratio): float
    {
        // L : 0 .. 0.3 .. 0.6
        $muL = $this->tri($ratio, 0.0, 0.3, 0.6);
        // M : 0.4 .. 0.6 .. 0.8
        $muM = $this->tri($ratio, 0.4, 0.6, 0.8);
        // H : 0.7 .. 1.0 .. 1.0 (puncak di 1.0)
        $muH = $this->tri($ratio, 0.7, 1.0, 1.0);

        $num = $muL * 60 + $muM * 85 + $muH * 98;
        $den = $muL + $muM + $muH;
        return $den > 0 ? ($num / $den) : 60.0;
    }

    /** Hitung skor per KPI (sesuai rumus skripsi) */
    private function scorePerKpi(string $tipe, float $target, float $realisasi): float
    {
        // Safety
        if ($target <= 0 && $tipe !== 'response') return 0.0;
        if ($tipe === 'response' && $realisasi <= 0) return 0.0;

        if ($tipe === 'response') {
            // Lebih cepat lebih baik -> y = t / r
            $y = $target / max(1e-9, $realisasi);
            return $y >= 1.0
                ? min(self::CAP_MAX, 100.0 * $y)
                : $this->fuzzyScoreResponse($y); // << perbaikan: pakai fuzzy khusus response
        }

        // Kuantitatif / Kualitatif / Persentase : lebih besar = lebih baik -> x = r / t
        $x = $realisasi / max(1e-9, $target);

        if ($x >= 1.0) {
            return min(self::CAP_MAX, 100.0 * $x);
        }

        if ($tipe === 'persentase') {
            return $this->fuzzyScorePercent($x);
        }

        // Kuantitatif / Kualitatif
        return $this->fuzzyScoreGeneral($x);
    }

    /* ====================== INDEX ====================== */

    public function index(Request $request)
    {
        $me      = Auth::user();
        $bulan   = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun   = $request->filled('tahun') ? (int)$request->tahun : null;
        $divId   = $request->filled('division_id') ? (int)$request->division_id : null;
        $search  = $request->input('search','');
        $perPage = (int)$request->input('per_page', 10);

        $divisions = Division::orderBy('name')->get();

        $usersQ = User::query()->with('division')
            ->when($search, function($q) use ($search){
                $q->where(function($qq) use ($search){
                    $qq->where('full_name','like',"%{$search}%")
                       ->orWhere('email','like',"%{$search}%")
                       ->orWhere('username','like',"%{$search}%");
                });
            });

        if ($me->role === 'leader') {
            $usersQ->where('division_id', $me->division_id)->where('role', 'karyawan');
        } elseif ($me->role === 'karyawan') {
            $usersQ->where('id', $me->id);
        } else { // owner/hr
            $usersQ->where('role', 'karyawan')
                   ->when($divId, fn($q)=>$q->where('division_id', $divId));
        }

        $users = $usersQ->orderBy('full_name')->paginate($perPage)->appends($request->all());

        $realizations = collect();
        if (!is_null($bulan) && !is_null($tahun)) {
            $realizations = KpiUmumRealization::whereIn('user_id', $users->pluck('id'))
                ->where('bulan',$bulan)->where('tahun',$tahun)
                ->get()->keyBy('user_id');
        }

        return view('realisasi-kpi-umum.index', [
            'users'      => $users,
            'realByUser' => $realizations,
            'me'         => $me,
            'bulan'      => $bulan,
            'tahun'      => $tahun,
            'bulanList'  => $this->bulanList,
            'divisions'  => $divisions,
            'division_id'=> $divId,
            'search'     => $search,
            'perPage'    => $perPage,
        ]);
    }

    /* ====================== CREATE ====================== */

    public function create(Request $request, User $user)
    {
        $me = Auth::user();
        if ($me->role !== 'leader' || $me->division_id !== $user->division_id || $user->role !== 'karyawan') {
            abort(403);
        }

        $bulan = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun = $request->filled('tahun') ? (int)$request->tahun : null;
        if (is_null($bulan) || is_null($tahun)) {
            return redirect()->route('realisasi-kpi-umum.index')->with('error','Pilih bulan & tahun dahulu.');
        }

        $kpis = KpiUmum::where('bulan',$bulan)->where('tahun',$tahun)->orderBy('nama')->get();
        if ($kpis->count() < 1) {
            return redirect()->route('realisasi-kpi-umum.index')->with('error','Tidak ada KPI Umum untuk periode ini.');
        }

        $real = KpiUmumRealization::with('items')
            ->where('user_id',$user->id)->where('bulan',$bulan)->where('tahun',$tahun)->first();

        return view('realisasi-kpi-umum.create', compact('user','me','bulan','tahun','kpis','real'));
    }

    /* ====================== STORE ====================== */

    public function store(Request $request, User $user)
    {
        $me = Auth::user();
        if ($me->role !== 'leader' || $me->division_id !== $user->division_id || $user->role !== 'karyawan') {
            abort(403);
        }

        $data = $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000|max:2100',
            'items' => 'required|array',
            'items.*.kpi_id'    => 'required|exists:kpi_umum,id',
            'items.*.tipe'      => 'required|in:kuantitatif,kualitatif,response,persentase',
            'items.*.satuan'    => 'nullable|string|max:50',
            'items.*.target'    => 'required|numeric',
            'items.*.realisasi' => 'required|numeric',
        ]);

        $bulan = (int)$data['bulan'];
        $tahun = (int)$data['tahun'];

        // Ambil bobot AHP per KPI yang dikirim
        $weights = KpiUmum::whereIn('id', collect($data['items'])->pluck('kpi_id'))
            ->get(['id', 'bobot'])
            ->keyBy('id');

        DB::transaction(function() use ($user, $data, $weights) {
            $bulan = (int)$data['bulan'];
            $tahun = (int)$data['tahun'];

            $real = KpiUmumRealization::firstOrCreate(
                ['user_id' => $user->id, 'bulan' => $bulan, 'tahun' => $tahun],
                ['division_id' => $user->division_id, 'status' => 'submitted', 'hr_note' => null, 'total_score' => 0]
            );
            if ($real->status !== 'submitted') {
                $real->update(['status' => 'submitted', 'hr_note' => null]);
            }

            $totalWeighted = 0.0;
            $sumW = 0.0;
            $scores = [];
            $kpiIds = [];

            $rawW = [];
            foreach ($data['items'] as $row) {
                $kpiId = (int)$row['kpi_id'];
                $kpiIds[] = $kpiId;
                $rawW[$kpiId] = (float)($weights[$kpiId]->bobot ?? 0);
            }

            if (empty($rawW)) {
                $rawW = array_fill_keys($kpiIds, 1.0);
            }

            $maxW = empty($rawW) ? 0 : max($rawW);
            if ($maxW > 1) {
                foreach ($rawW as $kpiId => $w) {
                    $rawW[$kpiId] = $w / 100.0;
                }
            }

            foreach ($data['items'] as $row) {
                $kpiId  = (int)$row['kpi_id'];
                $tipe   = $row['tipe'];
                $satuan = $row['satuan'] ?? null;
                $tgt    = (float)$row['target'];
                $realv  = (float)$row['realisasi'];

                $score = $this->scorePerKpi($tipe, $tgt, $realv);
                $scores[] = $score;

                KpiUmumRealizationItem::updateOrCreate(
                    ['realization_id' => $real->id, 'kpi_umum_id' => $kpiId],
                    ['tipe' => $tipe, 'satuan' => $satuan, 'target' => $tgt, 'realisasi' => $realv, 'score' => $score]
                );

                $w = (float)($rawW[$kpiId] ?? 0);
                $totalWeighted += $score * $w;
                $sumW          += $w;
            }

            $final = 0.0;
            if ($sumW > 0) {
                $final = $totalWeighted / $sumW;
            } else {
                $final = count($scores) ? array_sum($scores) / count($scores) : 0.0;
            }

            $real->forceFill(['total_score' => $final, 'status' => 'submitted'])->save();
        });

        return redirect()->route('realisasi-kpi-umum.index', ['bulan'=>$bulan, 'tahun'=>$tahun])
            ->with('success','Realisasi tersimpan. Menunggu verifikasi HR.');
    }

    /* ====================== SHOW ====================== */

    public function show(KpiUmumRealization $realization)
    {
        $realization->load(['user.division','items.kpi']);
        $me = Auth::user();

        if ($me->role === 'leader' && $me->division_id !== $realization->division_id) abort(403);
        if ($me->role === 'karyawan' && $me->id !== $realization->user_id) abort(403);

        return view('realisasi-kpi-umum.show', [
            'me'        => $me,
            'real'      => $realization,
            'bulanList' => $this->bulanList,
        ]);
    }

    /* ====================== APPROVE / REJECT ====================== */

    public function approve(Request $request, KpiUmumRealization $realization)
    {
        if (Auth::user()->role !== 'hr') abort(403);
        $realization->update(['status'=>'approved','hr_note'=>null]);
        return back()->with('success','Realisasi disetujui.');
    }

    public function reject(Request $request, KpiUmumRealization $realization)
    {
        if (Auth::user()->role !== 'hr') abort(403);
        $data = $request->validate(['hr_note'=>'required|string|max:1000']);
        $realization->update(['status'=>'rejected','hr_note'=>$data['hr_note']]);
        return back()->with('success','Realisasi ditolak dengan keterangan.');
    }
}
