<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiResponseRealizationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KpiDivisiResponseRealizationController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    /** INDEX: daftar karyawan sesuai role + status realisasi */
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

        // butuh filter lengkap
        $needDivForAdmin = in_array($me->role, ['owner','hr'], true) && empty($division_id);
        if (is_null($bulan) || is_null($tahun) || $needDivForAdmin) {
            $users = User::whereRaw('1=0')->paginate($perPage);
            return view('realisasi-kpi-divisi-response.index', [
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

        // hanya karyawan
        $usersQ->where('role', 'karyawan');

        $users = $usersQ->orderBy('full_name')->paginate($perPage)->appends($request->all());

        // ambil realisasi header by user
        $realByUser = KpiDivisiResponseRealization::where('bulan',$bulan)
            ->where('tahun',$tahun)
            ->whereIn('user_id', $users->pluck('id'))
            ->get()->keyBy('user_id');

        return view('realisasi-kpi-divisi-response.index', [
            'me'=>$me,'users'=>$users,'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$division_id,
            'divisions'=>$divisions,'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
            'realByUser'=>$realByUser,
        ]);
    }

    /** CREATE: Leader input realisasi untuk seorang karyawan di periode tsb */
    public function create(Request $request)
    {
        $me = Auth::user();
        abort_unless($me->role === 'leader', 403);

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'bulan'   => 'required|integer|min:1|max:12',
            'tahun'   => 'required|integer|min:2000|max:2100',
        ]);

        $user  = User::findOrFail((int)$request->user_id);
        abort_unless($user->division_id === $me->division_id, 403);
        abort_unless($user->role === 'karyawan', 403);

        $bulan = (int)$request->bulan;
        $tahun = (int)$request->tahun;

        // KPI response periode ini (target sama utk semua karyawan)
        $kpis = KpiDivisi::where('division_id',$user->division_id)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('tipe','response')
            ->orderBy('nama')->get();

        if ($kpis->isEmpty()) {
            return redirect()->route('realisasi-kpi-divisi-response.index', [
                'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$user->division_id
            ])->with('error','Tidak ada KPI Divisi bertipe response pada periode ini.');
        }

        // Existing (jika ajukan ulang)
        $real = KpiDivisiResponseRealization::where([
            'user_id'=>$user->id,'division_id'=>$user->division_id,
            'bulan'=>$bulan,'tahun'=>$tahun
        ])->first();

        $existing = [];
        if ($real) {
            $its = KpiDivisiResponseRealizationItem::where('realization_id',$real->id)->get();
            foreach ($its as $it) {
                $existing[$it->kpi_divisi_id] = [
                    'realization' => (float)$it->realization,
                    'score'       => $it->score !== null ? (float)$it->score : null,
                ];
            }
        }

        return view('realisasi-kpi-divisi-response.create', [
            'me'=>$me,'user'=>$user,'bulan'=>$bulan,'tahun'=>$tahun,
            'kpis'=>$kpis,'existing'=>$existing,'bulanList'=>$this->bulanList
        ]);
    }

    /** STORE: simpan pengajuan realisasi (leader) */
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

        $kpis = KpiDivisi::where('division_id',$user->division_id)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('tipe','response')->orderBy('nama')->get();

        if ($kpis->isEmpty()) {
            return back()->with('error','Tidak ada KPI response pada periode ini.')->withInput();
        }

        // siapkan baris item (target = target KPI, skor pakai response)
        $rows = [];
        foreach ($kpis as $k) {
            $kpiId = $k->id;
            $target = (float)($k->target ?? 0);
            $realz  = isset($data['real'][$kpiId]) ? (float)$data['real'][$kpiId] : 0;
            $score  = $this->scoreResponse($realz, $target);

            $rows[] = [
                'kpi_divisi_id' => $kpiId,
                'target'        => $target,
                'realization'   => $realz,
                'score'         => $score,
            ];
        }

        DB::transaction(function () use ($user, $bulan, $tahun, $me, $rows) {
            $real = KpiDivisiResponseRealization::updateOrCreate(
                ['user_id'=>$user->id,'division_id'=>$user->division_id,'bulan'=>$bulan,'tahun'=>$tahun],
                [
                    'status'=>'submitted',
                    'hr_note'=>null,
                    'created_by'=>$me->id,
                    'updated_by'=>$me->id,
                    'total_score'=>null
                ]
            );

            KpiDivisiResponseRealizationItem::where('realization_id',$real->id)->delete();
            $now = now();
            foreach ($rows as $r) {
                KpiDivisiResponseRealizationItem::create([
                    'realization_id'=>$real->id,
                    'kpi_divisi_id' =>$r['kpi_divisi_id'],
                    'target'        =>$r['target'],
                    'realization'   =>$r['realization'],
                    'score'         =>$r['score'],
                    'created_at'    =>$now,
                    'updated_at'    =>$now,
                ]);
            }
        });

        return redirect()->route('realisasi-kpi-divisi-response.index', [
            'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$user->division_id
        ])->with('success','Realisasi diajukan. Menunggu verifikasi HR.');
    }

    /** SHOW: detail pengajuan/hasil satu karyawan */
    public function show($id)
    {
        $me = Auth::user();
        $real = KpiDivisiResponseRealization::with('user','division')->findOrFail($id);

        if ($me->role === 'leader' && $me->division_id !== $real->division_id) abort(403);
        if ($me->role === 'karyawan' && $me->id !== $real->user_id) abort(403);

        $items = KpiDivisiResponseRealizationItem::where('realization_id',$real->id)->with('kpi')->get();

        // hitung total skor jika bobot AHP tersedia
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

        return view('realisasi-kpi-divisi-response.show', [
            'me'=>$me,'real'=>$real,'items'=>$items,'kpis'=>$kpis,'total'=>$total,'bulanList'=>$this->bulanList
        ]);
    }

    /** HR Approve */
    public function approve($id)
    {
        $me = Auth::user();
        abort_unless($me->role === 'hr', 403);

        $real = KpiDivisiResponseRealization::findOrFail($id);
        if ($real->status === 'approved') {
            return back()->with('success','Realisasi sudah disetujui.');
        }
        if ($real->status === 'stale') {
            return back()->with('error','Realisasi berstatus stale. Leader harus input ulang.');
        }

        $items = KpiDivisiResponseRealizationItem::where('realization_id',$real->id)->get();
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

    /** HR Reject */
    public function reject(Request $request, $id)
    {
        $me = Auth::user();
        abort_unless($me->role === 'hr', 403);

        $request->validate(['hr_note'=>'required|string']);
        $real = KpiDivisiResponseRealization::findOrFail($id);
        $real->update(['status'=>'rejected','hr_note'=>$request->hr_note,'total_score'=>null]);

        return back()->with('success','Realisasi ditolak.');
    }

    // ====== SCORING (response: lebih cepat = lebih baik) ======
    private function scoreResponse(float $realisasi, float $target): float
    {
        $eps = 1e-9;
        if ($target <= 0) {
            if ($realisasi <= 0) return 100.0;
            return 50.0; // fallback aman
        }

        if ($realisasi <= $target) {
            // Lebih cepat dari target → >= 100
            $score = 100.0 * ($target / max($realisasi, $eps));
            return round($score, 2);
        }

        // Lebih lambat dari target → < 100, fuzzy menurun
        // gunakan rasio x = target / realisasi ∈ (0,1)
        $x = max(0.0, min(1.0, $target / max($realisasi, $eps)));

        // Tiga himpunan: near, medium, far (segitiga)
        $muNear = 0.0; $muMed = 0.0; $muFar = 0.0;

        // Near (0.7..1.0)
        if      ($x <= 0.7) $muNear = 0.0;
        elseif  ($x <= 1.0) $muNear = ($x - 0.7) / (1.0 - 0.7 + $eps);
        else                $muNear = 1.0;

        // Medium (0.4..0.8, puncak 0.6)
        if      ($x <= 0.4) $muMed = 0.0;
        elseif  ($x <= 0.6) $muMed = ($x - 0.4) / (0.6 - 0.4 + $eps);
        elseif  ($x <= 0.8) $muMed = (0.8 - $x) / (0.8 - 0.6 + $eps);
        else                $muMed = 0.0;

        // Far (0.0..0.6)
        if      ($x <= 0.0) $muFar = 1.0;
        elseif  ($x <= 0.3) $muFar = 1.0; // jauh sekali
        elseif  ($x <= 0.6) $muFar = (0.6 - $x) / (0.6 - 0.3 + $eps);
        else                $muFar = 0.0;

        // Bobot output (skor)
        $wNear = 95; $wMed = 80; $wFar = 50;
        $den = $muNear + $muMed + $muFar;
        if ($den <= 0) return 50.0;

        $score = ($muNear*$wNear + $muMed*$wMed + $muFar*$wFar) / $den;
        return round($score, 2);
    }
}
