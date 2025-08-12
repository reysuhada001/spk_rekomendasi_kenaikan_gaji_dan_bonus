<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiDistribution;
use App\Models\KpiDivisiDistributionItem;
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKuantitatifRealizationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KpiDivisiKuantitatifRealizationController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    public function index(Request $request)
    {
        $me = Auth::user();
        $perPage = (int) $request->input('per_page', 10);
        $search  = $request->input('search', '');

        $bulan = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun = $request->filled('tahun') ? (int)$request->tahun : null;

        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;
        if ($me->role === 'leader' || $me->role === 'karyawan') {
            $division_id = $me->division_id;
        }

        $divisions = Division::orderBy('name')->get();

        $needDivForAdmin = in_array($me->role, ['owner','hr'], true) && empty($division_id);
        if (is_null($bulan) || is_null($tahun) || $needDivForAdmin) {
            $users = User::whereRaw('1=0')->paginate($perPage);
            return view('realisasi-kpi-divisi-kuantitatif.index', [
                'me'=>$me,'users'=>$users,'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$division_id,
                'divisions'=>$divisions,'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
                'realByUser'=>[],
            ]);
        }

        $usersQ = User::with('division')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('full_name','like',"%{$search}%")
                       ->orWhere('nik','like',"%{$search}%")
                       ->orWhere('email','like',"%{$search}%")
                       ->orWhere('username','like',"%{$search}%");
                });
            });

        if ($me->role === 'leader') {
            $usersQ->where('division_id', $me->division_id);
        } elseif ($me->role === 'karyawan') {
            $usersQ->where('id', $me->id);
        } elseif (in_array($me->role, ['owner','hr'], true)) {
            if (!empty($division_id)) $usersQ->where('division_id', $division_id);
        }

        $usersQ->where('role', 'karyawan');

        $users = $usersQ->orderBy('full_name')->paginate($perPage)->appends($request->all());

        $realByUser = KpiDivisiKuantitatifRealization::where('bulan',$bulan)
            ->where('tahun',$tahun)
            ->whereIn('user_id', $users->pluck('id'))
            ->get()->keyBy('user_id');

        return view('realisasi-kpi-divisi-kuantitatif.index', [
            'me'=>$me,'users'=>$users,'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$division_id,
            'divisions'=>$divisions,'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
            'realByUser'=>$realByUser,
        ]);
    }

    public function create(Request $request)
    {
        $me = Auth::user();
        abort_unless($me->role === 'leader', 403);

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'bulan'   => 'required|integer|min:1|max:12',
            'tahun'   => 'required|integer|min:2000|max:2100',
        ]);

        $user = User::findOrFail((int)$request->user_id);
        abort_unless($user->division_id === $me->division_id, 403);
        abort_unless($user->role === 'karyawan', 403);

        $bulan = (int)$request->bulan;
        $tahun = (int)$request->tahun;

        $dist = KpiDivisiDistribution::where('division_id',$user->division_id)
            ->where('bulan',$bulan)->where('tahun',$tahun)->first();

        if (!$dist || $dist->status === 'rejected' || $dist->status === 'stale') {
            return redirect()->route('realisasi-kpi-divisi-kuantitatif.index', [
                'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$user->division_id
            ])->with('error','Distribusi target belum tersedia/valid untuk periode ini.');
        }

        $kpis = KpiDivisi::where('division_id',$user->division_id)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('tipe','kuantitatif')->orderBy('nama')->get();

        if ($kpis->isEmpty()) {
            return redirect()->route('realisasi-kpi-divisi-kuantitatif.index', [
                'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$user->division_id
            ])->with('error','Tidak ada KPI Divisi bertipe kuantitatif pada periode ini.');
        }

        $targets = KpiDivisiDistributionItem::where('distribution_id',$dist->id)
            ->whereIn('kpi_divisi_id', $kpis->pluck('id'))
            ->where('user_id', $user->id)
            ->get()->keyBy('kpi_divisi_id');

        if ($targets->isEmpty()) {
            return redirect()->route('realisasi-kpi-divisi-kuantitatif.index', [
                'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$user->division_id
            ])->with('error','Target per-KPI untuk karyawan ini belum didistribusikan.');
        }

        $real = KpiDivisiKuantitatifRealization::where([
            'user_id'=>$user->id,'division_id'=>$user->division_id,
            'bulan'=>$bulan,'tahun'=>$tahun
        ])->first();

        $existing = [];
        if ($real) {
            $its = KpiDivisiKuantitatifRealizationItem::where('realization_id',$real->id)->get();
            foreach ($its as $it) {
                $existing[$it->kpi_divisi_id] = [
                    'realization' => (float)$it->realization,
                    'score'       => $it->score !== null ? (float)$it->score : null,
                ];
            }
        }

        return view('realisasi-kpi-divisi-kuantitatif.create', [
            'me'=>$me,'user'=>$user,'bulan'=>$bulan,'tahun'=>$tahun,
            'kpis'=>$kpis,'targets'=>$targets,'existing'=>$existing,'bulanList'=>$this->bulanList
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::user();
        abort_unless($me->role === 'leader', 403);

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'bulan'   => 'required|integer|min:1|max:12',
            'tahun'   => 'required|integer|min:2000|max:2100',
            'real'    => 'required|array',
        ]);

        $user = User::findOrFail((int)$data['user_id']);
        abort_unless($user->division_id === $me->division_id, 403);
        abort_unless($user->role === 'karyawan', 403);

        $bulan = (int)$data['bulan']; $tahun = (int)$data['tahun'];

        $dist = KpiDivisiDistribution::where('division_id',$user->division_id)
            ->where('bulan',$bulan)->where('tahun',$tahun)->first();

        if (!$dist || $dist->status === 'rejected' || $dist->status === 'stale') {
            return back()->with('error','Distribusi target belum tersedia/valid untuk periode ini.')->withInput();
        }

        $kpis = KpiDivisi::where('division_id',$user->division_id)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('tipe','kuantitatif')->orderBy('nama')->get();

        if ($kpis->isEmpty()) {
            return back()->with('error','Tidak ada KPI kuantitatif pada periode ini.')->withInput();
        }

        $distItems = KpiDivisiDistributionItem::where('distribution_id',$dist->id)
            ->whereIn('kpi_divisi_id', $kpis->pluck('id'))
            ->where('user_id',$user->id)
            ->get()->keyBy('kpi_divisi_id');

        if ($distItems->isEmpty()) {
            return back()->with('error','Target per-KPI untuk karyawan ini belum didistribusikan.')->withInput();
        }

        $rows = [];
        foreach ($kpis as $k) {
            $kpiId = $k->id;
            $target = (float)($distItems[$kpiId]->target ?? 0);
            $realz  = isset($data['real'][$kpiId]) ? (float)$data['real'][$kpiId] : 0;
            $score  = $this->scoreKuantitatif($realz, $target);

            $rows[] = [
                'kpi_divisi_id' => $kpiId,
                'target'        => $target,
                'realization'   => $realz,
                'score'         => $score,
            ];
        }

        DB::transaction(function () use ($user, $bulan, $tahun, $me, $rows) {
            $real = KpiDivisiKuantitatifRealization::updateOrCreate(
                ['user_id'=>$user->id,'division_id'=>$user->division_id,'bulan'=>$bulan,'tahun'=>$tahun],
                [
                    'status'=>'submitted',
                    'hr_note'=>null,
                    'created_by'=>$me->id,
                    'updated_by'=>$me->id,
                    'total_score'=>null
                ]
            );

            KpiDivisiKuantitatifRealizationItem::where('realization_id',$real->id)->delete();
            $now = now();
            foreach ($rows as $r) {
                KpiDivisiKuantitatifRealizationItem::create([
                    'realization_id'=>$real->id,
                    'user_id'       =>$user->id, // << WAJIB
                    'kpi_divisi_id' =>$r['kpi_divisi_id'],
                    'target'        =>$r['target'],
                    'realization'   =>$r['realization'],
                    'score'         =>$r['score'],
                    'created_at'    =>$now,
                    'updated_at'    =>$now,
                ]);
            }
        });

        return redirect()->route('realisasi-kpi-divisi-kuantitatif.index', [
            'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$user->division_id
        ])->with('success','Realisasi diajukan. Menunggu verifikasi HR.');
    }

    public function show($id)
    {
        $me = Auth::user();
        $real = KpiDivisiKuantitatifRealization::with('user','division')->findOrFail($id);

        if ($me->role === 'leader' && $me->division_id !== $real->division_id) abort(403);
        if ($me->role === 'karyawan' && $me->id !== $real->user_id) abort(403);

        $items = KpiDivisiKuantitatifRealizationItem::where('realization_id',$real->id)
            ->with('kpi')->get();

        $kpiIds = $items->pluck('kpi_divisi_id');
        $kpis   = KpiDivisi::whereIn('id',$kpiIds)->get()->keyBy('id');

        $total = null;
        if ($items->count() > 0) {
            $sumWS = 0.0;
            foreach ($items as $it) {
                $w = (float)($kpis[$it->kpi_divisi_id]->bobot ?? 0);
                $sumWS += $w * (float)$it->score;
            }
            $total = round($sumWS, 2);
        }

        return view('realisasi-kpi-divisi-kuantitatif.show', [
            'me'=>$me,'real'=>$real,'items'=>$items,'kpis'=>$kpis,'total'=>$total,'bulanList'=>$this->bulanList
        ]);
    }

    public function approve($id)
    {
        $me = Auth::user();
        abort_unless($me->role === 'hr', 403);

        $real = KpiDivisiKuantitatifRealization::findOrFail($id);
        if ($real->status === 'approved') {
            return back()->with('success','Realisasi sudah disetujui.');
        }
        if ($real->status === 'stale') {
            return back()->with('error','Realisasi berstatus stale. Leader harus input ulang.');
        }

        $items = KpiDivisiKuantitatifRealizationItem::where('realization_id',$real->id)->get();
        $kpis  = KpiDivisi::whereIn('id', $items->pluck('kpi_divisi_id'))->get()->keyBy('id');

        $total = null;
        if ($items->count() > 0) {
            $sumWS = 0.0;
            foreach ($items as $it) {
                $w = (float)($kpis[$it->kpi_divisi_id]->bobot ?? 0);
                $sumWS += $w * (float)$it->score;
            }
            $total = round($sumWS, 2);
        }

        $real->update([
            'status' => 'approved',
            'hr_note'=> null,
            'total_score' => $total
        ]);

        return back()->with('success','Realisasi disetujui.');
    }

    public function reject(Request $request, $id)
    {
        $me = Auth::user();
        abort_unless($me->role === 'hr', 403);

        $request->validate(['hr_note'=>'required|string']);
        $real = KpiDivisiKuantitatifRealization::findOrFail($id);
        $real->update(['status'=>'rejected','hr_note'=>$request->hr_note,'total_score'=>null]);

        return back()->with('success','Realisasi ditolak.');
    }

    private function scoreKuantitatif(float $real, float $target): float
    {
        if ($target <= 0) return $real > 0 ? 150.0 : 0.0;
        if ($real >= $target) return min(200.0, round(100.0 * ($real / $target), 2));

        $ratio = max(0.0, min(1.0, $real / $target));
        return round($this->fuzzyBelowTargetScore($ratio), 2);
    }

    private function fuzzyBelowTargetScore(float $x): float
    {
        $muL = 0.0; $muM = 0.0; $muH = 0.0;

        if     ($x <= 0.3) $muL = ($x - 0.0) / (0.3 - 0.0 + 1e-9);
        elseif ($x <= 0.6) $muL = (0.6 - $x) / (0.6 - 0.3 + 1e-9);
        $muL = max(0.0, min(1.0, $muL));

        if     ($x <= 0.4) $muM = 0.0;
        elseif ($x <= 0.7) $muM = ($x - 0.4) / (0.7 - 0.4 + 1e-9);
        else               $muM = (1.0 - $x) / (1.0 - 0.7 + 1e-9);
        $muM = max(0.0, min(1.0, $muM));

        if     ($x <= 0.6) $muH = 0.0;
        elseif ($x <= 0.9) $muH = ($x - 0.6) / (0.9 - 0.6 + 1e-9);
        else               $muH = (1.0 - $x) / (1.0 - 0.9 + 1e-9);
        $muH = max(0.0, min(1.0, $muH));

        $wL = 50; $wM = 80; $wH = 95;
        $den = $muL + $muM + $muH;
        if ($den <= 0) return 0.0;
        return ($muL*$wL + $muM*$wM + $muH*$wH) / $den;
    }
}
