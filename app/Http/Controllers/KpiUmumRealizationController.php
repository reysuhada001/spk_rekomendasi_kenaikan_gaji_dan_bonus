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
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    // ===== Fuzzy & Scoring =====

    // Clamp helper
    private function clamp(float $x, float $a, float $b): float { return max($a, min($b, $x)); }

    // Fuzzy score saat rasio < 1 (di bawah target). r in [0..1]
    private function fuzzyBelowTarget(float $r): float
    {
        // Membership "Low" (0..0.8), "Near" (0.6..1.0)
        $muLow  = $r <= 0.4 ? 1.0 : ($r >= 0.8 ? 0.0 : (0.8 - $r) / 0.4);
        $muNear = $r <= 0.6 ? 0.0 : ($r >= 1.0 ? 1.0 : ($r - 0.6) / 0.4);

        // Output anchors: Low->60, Near->90 (bisa disetel)
        $num = $muLow * 60 + $muNear * 90;
        $den = $muLow + $muNear;
        return $den > 0 ? ($num / $den) : 50;
    }

    // Hitung skor per KPI
    private function scorePerKpi(string $tipe, float $target, float $realisasi): float
    {
        if ($target <= 0 && $tipe !== 'response') {
            return 0; // fallback
        }

        if ($tipe === 'response') {
            // Lebih cepat lebih baik -> gunakan ratio = target / realisasi
            if ($realisasi <= 0) return 0;
            $ratio = $target / $realisasi;
        } else {
            // Lebih besar lebih baik
            $ratio = $realisasi / ($target ?: 1e-12);
        }

        if ($ratio >= 1.0) {
            // Di atas/tepat target -> >100/100, tidak pakai fuzzy
            return 100.0 * $ratio;
        }

        // Di bawah target -> fuzzy (0..100)
        return $this->fuzzyBelowTarget($ratio);
    }

    // ===== INDEX (list pegawai + status tombol) =====
    public function index(Request $request)
    {
        $me      = Auth::user();
        $bulan   = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun   = $request->filled('tahun') ? (int)$request->tahun : null;
        $divId   = $request->filled('division_id') ? (int)$request->division_id : null;
        $search  = $request->input('search','');
        $perPage = (int)$request->input('per_page', 10);

        $divisions = Division::orderBy('name')->get();

        // Penentuan scope user (yang ditampilkan)
        $usersQ = User::query()->with('division')
            ->when($search, function($q) use ($search){
                $q->where(function($qq) use ($search){
                    $qq->where('full_name','like',"%{$search}%")
                       ->orWhere('email','like',"%{$search}%")
                       ->orWhere('username','like',"%{$search}%");
                });
            });

        if ($me->role === 'leader') {
            $usersQ->where('division_id', $me->division_id)
                   ->where('role', 'karyawan');
        } elseif ($me->role === 'karyawan') {
            $usersQ->where('id', $me->id);
        } else { // owner/hr
            $usersQ->where('role', 'karyawan')
                   ->when($divId, fn($q)=>$q->where('division_id', $divId));
        }

        $users = $usersQ->orderBy('full_name')->paginate($perPage)->appends($request->all());

        // Ambil realization per user untuk periode (kalau bulan & tahun dipilih)
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

    // ===== CREATE (leader input untuk karyawan divisinya) =====
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

        // Jika pernah submit/reject, prefill
        $real = KpiUmumRealization::with('items')
            ->where('user_id',$user->id)->where('bulan',$bulan)->where('tahun',$tahun)->first();

        return view('realisasi-kpi-umum.create', compact('user','me','bulan','tahun','kpis','real'));
    }

    // ===== STORE (leader simpan -> status submitted) =====
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

        $bulan = (int)$data['bulan']; $tahun = (int)$data['tahun'];

        // Ambil bobot AHP
        $weights = KpiUmum::whereIn('id', collect($data['items'])->pluck('kpi_id'))
            ->get(['id','bobot'])->keyBy('id');

        DB::transaction(function() use ($user,$data,$weights,$bulan,$tahun,$me) {

            // Upsert realization (unique user/bulan/tahun)
            $real = KpiUmumRealization::updateOrCreate(
                ['user_id'=>$user->id,'bulan'=>$bulan,'tahun'=>$tahun],
                ['division_id'=>$user->division_id,'status'=>'submitted','hr_note'=>null,'total_score'=>0]
            );

            $totalWeighted = 0.0; $sumW = 0.0;

            foreach ($data['items'] as $row) {
                $kpiId = (int)$row['kpi_id'];
                $tipe  = $row['tipe'];
                $satuan= $row['satuan'] ?? null;
                $tgt   = (float)$row['target'];
                $realv = (float)$row['realisasi'];

                $score = $this->scorePerKpi($tipe, $tgt, $realv);

                KpiUmumRealizationItem::updateOrCreate(
                    ['realization_id'=>$real->id,'kpi_umum_id'=>$kpiId],
                    ['tipe'=>$tipe,'satuan'=>$satuan,'target'=>$tgt,'realisasi'=>$realv,'score'=>$score]
                );

                $w = (float)($weights[$kpiId]->bobot ?? 0);
                if ($w > 0) {
                    $totalWeighted += $score * $w;
                    $sumW          += $w;
                }
            }

            $final = $sumW > 0 ? ($totalWeighted / $sumW) : 0.0;
            $real->update(['total_score'=>$final, 'status'=>'submitted']);
        });

        return redirect()->route('realisasi-kpi-umum.index', ['bulan'=>$bulan, 'tahun'=>$tahun])
            ->with('success','Realisasi tersimpan. Menunggu verifikasi HR.');
    }

    // ===== SHOW (detail untuk semua role; HR punya ACC/Tolak) =====
    public function show(KpiUmumRealization $realization)
    {
        $realization->load(['user.division','items.kpi']);
        $me = Auth::user();

        // Akses: owner/hr bisa lihat semua, leader hanya divisinya, karyawan hanya dirinya
        if ($me->role === 'leader' && $me->division_id !== $realization->division_id) abort(403);
        if ($me->role === 'karyawan' && $me->id !== $realization->user_id) abort(403);

        return view('realisasi-kpi-umum.show', [
            'me' => $me,
            'real' => $realization,
            'bulanList' => $this->bulanList,
        ]);
    }

    // ===== APPROVE / REJECT (HR) =====
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
