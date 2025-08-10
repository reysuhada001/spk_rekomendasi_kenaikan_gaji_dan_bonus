<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiDistribution;
use App\Models\KpiDivisiDistributionItem;
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKuantitatifRealizationItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KpiDivisiDistributionController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    /** INDEX — daftar KPI kuantitatif per (divisi, bulan, tahun) */
    public function index(Request $request)
    {
        $me      = Auth::user();
        $perPage = (int) $request->input('per_page', 10);
        $search  = $request->input('search', '');
        $bulan   = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun   = $request->filled('tahun') ? (int)$request->tahun : null;

        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;
        if (in_array($me->role, ['leader','karyawan'], true)) {
            $division_id = $me->division_id;
        }

        $divisions = Division::orderBy('name')->get();
        $needDivForAdmin = in_array($me->role, ['owner','hr'], true) && empty($division_id);

        if (is_null($bulan) || is_null($tahun) || $needDivForAdmin) {
            $kpis = KpiDivisi::whereRaw('1=0')->paginate($perPage)->appends($request->all());
            return view('distribusi-kpi-divisi.index', compact(
                'kpis','me','bulan','tahun','division_id','divisions','perPage','search'
            ) + ['bulanList'=>$this->bulanList,'distribution'=>null,'itemsCountByKpi'=>[]]);
        }

        $kpis = KpiDivisi::with('division')
            ->where('division_id',$division_id)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->where('tipe','kuantitatif')
            ->when($search, fn($q)=>$q->where('nama','like',"%{$search}%"))
            ->orderBy('nama')
            ->paginate($perPage)->appends($request->all());

        $distribution = KpiDivisiDistribution::where('division_id',$division_id)
            ->where('bulan',$bulan)->where('tahun',$tahun)->first();

        $itemsCountByKpi = [];
        if ($distribution) {
            $itemsCountByKpi = KpiDivisiDistributionItem::where('distribution_id',$distribution->id)
                ->select('kpi_divisi_id', DB::raw('count(*) c'))
                ->groupBy('kpi_divisi_id')
                ->pluck('c','kpi_divisi_id')->toArray();
        }

        return view('distribusi-kpi-divisi.index', [
            'kpis'=>$kpis,'me'=>$me,'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$division_id,
            'divisions'=>$divisions,'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
            'distribution'=>$distribution,'itemsCountByKpi'=>$itemsCountByKpi,
        ]);
    }

    /** FORM input distribusi utk satu KPI (pakai ?kpi_id=) */
    public function create(Request $request)
    {
        $me = Auth::user();
        abort_unless($me->role === 'leader', 403);

        $kpi_id = (int) $request->query('kpi_id');
        $kpi = KpiDivisi::findOrFail($kpi_id);
        abort_unless($kpi->tipe === 'kuantitatif', 404);
        abort_unless($me->division_id === $kpi->division_id, 403);

        $employees = User::where('division_id',$kpi->division_id)
            ->where('role','karyawan')->orderBy('full_name')->get();

        $distribution = KpiDivisiDistribution::firstOrCreate(
            ['division_id'=>$kpi->division_id,'bulan'=>$kpi->bulan,'tahun'=>$kpi->tahun],
            ['status'=>'submitted','created_by'=>$me->id]
        );

        $existing = KpiDivisiDistributionItem::where('distribution_id',$distribution->id)
            ->where('kpi_divisi_id',$kpi->id)->pluck('target','user_id')->toArray();

        return view('distribusi-kpi-divisi.create', [
            'kpi'=>$kpi,'employees'=>$employees,'existing'=>$existing,
            'distribution'=>$distribution,'bulanList'=>$this->bulanList,
        ]);
    }

    /** SIMPAN distribusi utk satu KPI (body: kpi_id, alloc[]) */
    public function store(Request $request)
    {
        $me = Auth::user();
        abort_unless($me->role === 'leader', 403);

        $data = $request->validate([
            'kpi_id' => 'required|integer|exists:kpi_divisi,id',
            'alloc'  => 'required|array',
        ]);

        $kpi = KpiDivisi::findOrFail((int)$data['kpi_id']);
        abort_unless($kpi->tipe === 'kuantitatif', 404);
        abort_unless($me->division_id === $kpi->division_id, 403);

        // validasi karyawan yang sah
        $validEmp = User::where('division_id',$kpi->division_id)
            ->where('role','karyawan')->pluck('id')->all();
        $validMap = array_flip($validEmp);

        $sum = 0.0; $rows = [];
        foreach ($data['alloc'] as $userId => $val) {
            if (!isset($validMap[(int)$userId])) continue;
            $v = (float)($val ?? 0);
            $sum += $v;
            $rows[] = ['user_id'=>(int)$userId, 'target'=>$v];
        }

        if (abs($sum - (float)$kpi->target) > 0.0001) {
            return back()->with('error','Total alokasi harus sama dengan target KPI.')->withInput();
        }

        DB::transaction(function() use ($kpi, $me, $rows) {
            $dist = KpiDivisiDistribution::updateOrCreate(
                ['division_id'=>$kpi->division_id,'bulan'=>$kpi->bulan,'tahun'=>$kpi->tahun],
                ['status'=>'submitted','hr_note'=>null,'created_by'=>$me->id]
            );

            // hapus item lama utk KPI ini
            KpiDivisiDistributionItem::where('distribution_id',$dist->id)
                ->where('kpi_divisi_id',$kpi->id)->delete();

            // insert item baru
            $now = now();
            foreach ($rows as $r) {
                KpiDivisiDistributionItem::create([
                    'distribution_id'=>$dist->id,
                    'kpi_divisi_id'  =>$kpi->id,
                    'user_id'        =>$r['user_id'],
                    'target'         =>$r['target'],
                    'created_at'     =>$now,
                    'updated_at'     =>$now,
                ]);
            }

            // =========== STALE realisasi terkait periode/divisi ===========
            $realIds = KpiDivisiKuantitatifRealization::where('division_id',$kpi->division_id)
                ->where('bulan',$kpi->bulan)->where('tahun',$kpi->tahun)
                ->pluck('id');

            if ($realIds->isNotEmpty()) {
                // header → tandai stale & reset total
                KpiDivisiKuantitatifRealization::whereIn('id',$realIds)->update([
                    'status'      => 'stale',
                    'hr_note'     => 'Perlu input ulang: distribusi target diperbarui.',
                    'total_score' => null,
                ]);

                // hapus item realisasi hanya untuk KPI yang berubah
                KpiDivisiKuantitatifRealizationItem::whereIn('realization_id',$realIds)
                    ->where('kpi_divisi_id',$kpi->id)
                    ->delete();
            }
        });

        return redirect()->route('distribusi-kpi-divisi.index', [
            'bulan'=>$kpi->bulan,'tahun'=>$kpi->tahun,'division_id'=>$kpi->division_id
        ])->with('success','Distribusi target KPI berhasil diajukan. Realisasi terkait ditandai perlu input ulang.');
    }

    /** SHOW detail distribusi per KPI (?distribution_id=&kpi_id=) */
    public function show(Request $request)
    {
        $me = Auth::user();

        $request->validate([
            'distribution_id' => 'required|integer|exists:kpi_divisi_distributions,id',
            'kpi_id'          => 'required|integer|exists:kpi_divisi,id',
        ]);

        $distribution = KpiDivisiDistribution::findOrFail((int)$request->distribution_id);
        $kpi          = KpiDivisi::findOrFail((int)$request->kpi_id);

        abort_unless(
            $kpi->division_id === $distribution->division_id
            && $kpi->bulan === $distribution->bulan
            && $kpi->tahun === $distribution->tahun,
            404
        );

        if ($me->role === 'leader' && $me->division_id !== $distribution->division_id) abort(403);
        if ($me->role === 'karyawan' && $me->division_id !== $distribution->division_id) abort(403);

        $employees = User::where('division_id',$distribution->division_id)
            ->where('role','karyawan')->orderBy('full_name')->get();

        $alloc = KpiDivisiDistributionItem::where('distribution_id',$distribution->id)
            ->where('kpi_divisi_id',$kpi->id)->pluck('target','user_id')->toArray();

        return view('distribusi-kpi-divisi.show', [
            'distribution'=>$distribution,'kpi'=>$kpi,'employees'=>$employees,
            'alloc'=>$alloc,'bulanList'=>$this->bulanList,'me'=>$me,
        ]);
    }

    /** HR: ACC distribusi periode/divisi (bukan per KPI) */
    public function approve(KpiDivisiDistribution $distribution)
    {
        if ($distribution->status === 'approved') {
            return back()->with('success','Distribusi sudah disetujui.');
        }
        $distribution->update(['status'=>'approved','hr_note'=>null]);
        return back()->with('success','Distribusi disetujui.');
    }

    /** HR: Tolak distribusi periode/divisi */
    public function reject(Request $request, KpiDivisiDistribution $distribution)
    {
        $data = $request->validate(['hr_note'=>'required|string']);
        $distribution->update(['status'=>'rejected','hr_note'=>$data['hr_note']]);
        return back()->with('success','Distribusi ditolak.');
    }
}
