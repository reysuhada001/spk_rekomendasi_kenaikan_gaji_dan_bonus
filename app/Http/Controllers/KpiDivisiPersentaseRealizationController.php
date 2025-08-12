<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiPersentaseRealization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KpiDivisiPersentaseRealizationController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    /** INDEX: daftar KPI Divisi tipe persentase per periode (bukan daftar karyawan) */
    public function index(Request $request)
    {
        $me      = Auth::user();
        $perPage = (int) $request->input('per_page', 10);
        $search  = $request->input('search', '');
        $bulan   = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun   = $request->filled('tahun') ? (int)$request->tahun : null;
        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;

        // leader/karyawan: otomatis pakai divisi sendiri
        if (in_array($me->role, ['leader','karyawan'], true)) {
            $division_id = $me->division_id;
        }

        $divisions = Division::orderBy('name')->get();

        // HR/Owner wajib pilih divisi; semua role wajib pilih bulan & tahun
        $needDivForAdmin = in_array($me->role, ['owner','hr'], true) && empty($division_id);
        if (is_null($bulan) || is_null($tahun) || $needDivForAdmin) {
            $kpis = KpiDivisi::whereRaw('1=0')->paginate($perPage);
            return view('realisasi-kpi-divisi-persentase.index', [
                'me'=>$me,'kpis'=>$kpis,'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$division_id,
                'divisions'=>$divisions,'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
                'realByKpi'=>[],
            ]);
        }

        $kpis = KpiDivisi::with('division')
            ->where('tipe','persentase')
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->when($division_id, fn($q)=>$q->where('division_id',$division_id))
            ->when($search, fn($q)=>$q->where('nama','like',"%{$search}%"))
            ->orderBy('nama')
            ->paginate($perPage)->appends($request->all());

        $realByKpi = KpiDivisiPersentaseRealization::where('bulan',$bulan)
            ->where('tahun',$tahun)
            ->whereIn('kpi_divisi_id', $kpis->pluck('id'))
            ->get()->keyBy('kpi_divisi_id');

        return view('realisasi-kpi-divisi-persentase.index', [
            'me'=>$me,'kpis'=>$kpis,'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$division_id,
            'divisions'=>$divisions,'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
            'realByKpi'=>$realByKpi,
        ]);
    }

    /** CREATE: Leader input sekali per KPI (periode ikut KPI) */
    public function create(Request $request)
    {
        $me = Auth::user();
        abort_unless($me->role === 'leader', 403);

        $request->validate(['kpi_divisi_id'=>'required|integer|exists:kpi_divisi,id']);
        $kpi = KpiDivisi::findOrFail((int)$request->kpi_divisi_id);

        abort_unless($kpi->tipe === 'persentase', 403);
        abort_unless($kpi->division_id === $me->division_id, 403);

        $existing = KpiDivisiPersentaseRealization::where('kpi_divisi_id',$kpi->id)->first();

        return view('realisasi-kpi-divisi-persentase.create', [
            'me'=>$me,'kpi'=>$kpi,'existing'=>$existing,'bulanList'=>$this->bulanList
        ]);
    }

    /** STORE: Leader submit realisasi (sekali per KPI) */
    public function store(Request $request)
    {
        $me = Auth::user();
        abort_unless($me->role === 'leader', 403);

        $data = $request->validate([
            'kpi_divisi_id' => 'required|integer|exists:kpi_divisi,id',
            'realization'   => 'required|numeric'
        ]);

        $kpi = KpiDivisi::findOrFail((int)$data['kpi_divisi_id']);
        abort_unless($kpi->tipe === 'persentase', 403);
        abort_unless($kpi->division_id === $me->division_id, 403);

        $target = (float)($kpi->target ?? 0);
        $real   = (float)$data['realization'];
        $score  = $this->scorePercentage($real, $target);

        DB::transaction(function () use ($kpi, $me, $target, $real, $score) {
            KpiDivisiPersentaseRealization::updateOrCreate(
                ['kpi_divisi_id'=>$kpi->id],
                [
                    'division_id' => $kpi->division_id,
                    'bulan'       => $kpi->bulan,
                    'tahun'       => $kpi->tahun,
                    'target'      => $target,
                    'realization' => $real,
                    'score'       => $score,
                    'status'      => 'submitted',
                    'hr_note'     => null,
                    'created_by'  => $me->id,
                    'updated_by'  => $me->id,
                ]
            );
        });

        return redirect()->route('realisasi-kpi-divisi-persentase.index', [
            'bulan'=>$kpi->bulan,'tahun'=>$kpi->tahun,'division_id'=>$kpi->division_id
        ])->with('success','Realisasi KPI (persentase) diajukan. Menunggu verifikasi HR.');
    }

    /** SHOW: Detail satu realisasi KPI persentase */
    public function show($id)
    {
        $me = Auth::user();
        $real = KpiDivisiPersentaseRealization::with('kpi','division')->findOrFail($id);

        if ($me->role === 'leader' && $me->division_id !== $real->division_id) abort(403);
        if ($me->role === 'karyawan' && $me->division_id !== $real->division_id) abort(403);

        return view('realisasi-kpi-divisi-persentase.show', [
            'me'=>$me,'real'=>$real,'bulanList'=>$this->bulanList
        ]);
    }

    /** HR Approve */
    public function approve($id)
    {
        $me = Auth::user();
        abort_unless($me->role === 'hr', 403);

        $real = KpiDivisiPersentaseRealization::findOrFail($id);
        if ($real->status === 'approved') {
            return back()->with('success','Realisasi sudah disetujui.');
        }
        if ($real->status === 'stale') {
            return back()->with('error','Realisasi berstatus stale. Leader harus input ulang.');
        }

        $real->update(['status'=>'approved','hr_note'=>null]);
        return back()->with('success','Realisasi disetujui.');
    }

    /** HR Reject */
    public function reject(Request $request, $id)
    {
        $me = Auth::user();
        abort_unless($me->role === 'hr', 403);

        $request->validate(['hr_note'=>'required|string']);
        $real = KpiDivisiPersentaseRealization::findOrFail($id);
        $real->update(['status'=>'rejected','hr_note'=>$request->hr_note]);

        return back()->with('success','Realisasi ditolak.');
    }

    /** Skoring Persentase: lebih besar lebih baik; < target pakai fuzzy naik */
    private function scorePercentage(float $real, float $target): float
    {
        $eps = 1e-9;
        if ($target <= 0) {
            if ($real <= 0) return 100.0;
            return 150.0; // jika target 0 tapi ada realisasi >0 → kasih reward
        }

        if ($real >= $target) {
            // cap? biarkan >100 sesuai aturan kamu
            $score = 100.0 * ($real / $target);
            return round($score, 2);
        }

        // real < target → fuzzy naik.
        $x = max(0.0, min(1.0, $real / max($target,$eps))); // 0..1 (proporsi tercapai)
        // low (0..0.6), med (0.4..0.8 peak 0.6), high (0.7..1.0)
        $muLow=0;$muMed=0;$muHigh=0;

        if      ($x <= 0.3) $muLow = 1.0;
        elseif  ($x <= 0.6) $muLow = (0.6 - $x) / (0.6 - 0.3 + $eps);
        else                $muLow = 0.0;

        if      ($x <= 0.4) $muMed = 0.0;
        elseif  ($x <= 0.6) $muMed = ($x - 0.4) / (0.6 - 0.4 + $eps);
        elseif  ($x <= 0.8) $muMed = (0.8 - $x) / (0.8 - 0.6 + $eps);
        else                $muMed = 0.0;

        if      ($x <= 0.7) $muHigh = 0.0;
        elseif  ($x <= 1.0) $muHigh = ($x - 0.7) / (1.0 - 0.7 + $eps);
        else                $muHigh = 1.0;

        // output weights
        $wLow=60; $wMed=85; $wHigh=98;
        $den = $muLow + $muMed + $muHigh;
        if ($den <= 0) return 60.0;

        $score = ($muLow*$wLow + $muMed*$wMed + $muHigh*$wHigh) / $den;
        return round($score, 2);
    }
}
