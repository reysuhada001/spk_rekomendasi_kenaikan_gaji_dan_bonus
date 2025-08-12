<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\KpiDivisi;

// Distribusi target (khusus kuantitatif)
use App\Models\KpiDivisiDistribution;
use App\Models\KpiDivisiDistributionItem;

// Realisasi KUANTITATIF
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKuantitatifRealizationItem;

// Realisasi KUALITATIF
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiKualitatifRealizationItem;

// Realisasi RESPONSE
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiResponseRealizationItem;

// Realisasi PERSENTASE (per KPI, bukan per karyawan)
use App\Models\KpiDivisiPersentaseRealization;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KpiDivisiController extends Controller
{
    public function __construct()
    {
        // Index bisa diakses semua role; CRUD hanya HR
        $this->middleware('role:hr')->except(['index']);
    }

    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    /** List KPI Divisi (semua role boleh lihat) */
    public function index(Request $request)
    {
        $me = Auth::user();
        $perPage = (int) $request->input('per_page', 10);
        $search  = $request->input('search', '');

        $kpis = KpiDivisi::with('division')
            ->when($search, fn($q)=>$q->where(function($qq)use($search){
                $qq->where('nama','like',"%{$search}%")
                   ->orWhere('tipe','like',"%{$search}%");
            }))
            ->orderBy('tahun','desc')
            ->orderBy('bulan','desc')
            ->orderBy('division_id')
            ->orderBy('nama')
            ->paginate($perPage)
            ->appends($request->all());

        $bulanList = $this->bulanList;
        $divisions = Division::orderBy('name')->get();

        return view('kpi-divisi.index', compact('kpis','me','bulanList','divisions','perPage','search'));
    }

    /** Tambah KPI Divisi (HR) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'division_id' => 'required|integer|exists:divisions,id',
            'nama'        => 'required|string|max:255|unique:kpi_divisi,nama,NULL,id,division_id,'.$request->division_id.',bulan,'.$request->bulan.',tahun,'.$request->tahun,
            'tipe'        => 'required|in:kuantitatif,kualitatif,response,persentase',
            'satuan'      => 'nullable|string|max:50',
            'target'      => 'required|numeric',
            'bulan'       => 'required|integer|min:1|max:12',
            'tahun'       => 'required|integer|min:2000|max:2100',
        ]);

        DB::transaction(function () use ($data) {
            // 1) Buat KPI (bobot null → wajib AHP ulang)
            $kpi = KpiDivisi::create($data + ['bobot'=>null]);

            // 2) Reset AHP (bobot) untuk SEMUA KPI dalam periode & divisi yang sama
            KpiDivisi::where('division_id', $kpi->division_id)
                ->where('bulan', $kpi->bulan)
                ->where('tahun', $kpi->tahun)
                ->update(['bobot' => null]);

            // 3) Distribusi → stale (khusus kuantitatif)
            KpiDivisiDistribution::where('division_id', $kpi->division_id)
                ->where('bulan', $kpi->bulan)
                ->where('tahun', $kpi->tahun)
                ->update(['status'=>'stale','hr_note'=>'Perlu input ulang: KPI Divisi berubah.']);

            // 4) Realisasi KUANTITATIF → stale & bersihkan item KPI ini
            $realQIds = KpiDivisiKuantitatifRealization::where('division_id',$kpi->division_id)
                ->where('bulan',$kpi->bulan)->where('tahun',$kpi->tahun)
                ->pluck('id');
            if ($realQIds->isNotEmpty()) {
                KpiDivisiKuantitatifRealization::whereIn('id',$realQIds)->update([
                    'status' => 'stale',
                    'hr_note'=> 'Perlu input ulang: KPI Divisi berubah.',
                    'total_score'=> null
                ]);
                KpiDivisiKuantitatifRealizationItem::whereIn('realization_id',$realQIds)
                    ->where('kpi_divisi_id',$kpi->id)->delete();
            }

            // 5) Realisasi KUALITATIF → stale & bersihkan item KPI ini
            $realKIds = KpiDivisiKualitatifRealization::where('division_id',$kpi->division_id)
                ->where('bulan',$kpi->bulan)->where('tahun',$kpi->tahun)
                ->pluck('id');
            if ($realKIds->isNotEmpty()) {
                KpiDivisiKualitatifRealization::whereIn('id',$realKIds)->update([
                    'status' => 'stale',
                    'hr_note'=> 'Perlu input ulang: KPI Divisi kualitatif berubah.',
                    'total_score'=> null
                ]);
                KpiDivisiKualitatifRealizationItem::whereIn('realization_id',$realKIds)
                    ->where('kpi_divisi_id',$kpi->id)->delete();
            }

            // 6) Realisasi RESPONSE → stale & bersihkan item KPI ini
            $realRIds = KpiDivisiResponseRealization::where('division_id', $kpi->division_id)
                ->where('bulan', $kpi->bulan)->where('tahun', $kpi->tahun)
                ->pluck('id');
            if ($realRIds->isNotEmpty()) {
                KpiDivisiResponseRealization::whereIn('id',$realRIds)->update([
                    'status'      => 'stale',
                    'hr_note'     => 'Perlu input ulang: KPI Divisi response berubah.',
                    'total_score' => null,
                ]);
                KpiDivisiResponseRealizationItem::whereIn('realization_id',$realRIds)
                    ->where('kpi_divisi_id',$kpi->id)->delete();
            }

            // 7) Realisasi PERSENTASE → stale (per KPI; cukup set status & kosongkan skor)
            if ($kpi->tipe === 'persentase') {
                KpiDivisiPersentaseRealization::where('kpi_divisi_id', $kpi->id)->update([
                    'status'  => 'stale',
                    'hr_note' => 'Perlu input ulang: KPI Divisi persentase berubah.',
                    'score'   => null,
                ]);
            }

            // 8) (Opsional) Bersihkan distribusi item KPI ini
            KpiDivisiDistribution::where('division_id',$kpi->division_id)
                ->where('bulan',$kpi->bulan)->where('tahun',$kpi->tahun)
                ->each(function($dist) use ($kpi){
                    KpiDivisiDistributionItem::where('distribution_id',$dist->id)
                        ->where('kpi_divisi_id',$kpi->id)->delete();
                });
        });

        return redirect()->route('kpi-divisi.index')->with('success','KPI Divisi berhasil ditambahkan.');
    }

    /** Update KPI Divisi (HR) */
    public function update(Request $request, KpiDivisi $kpiDivisi)
    {
        $data = $request->validate([
            'division_id' => 'required|integer|exists:divisions,id',
            'nama'        => 'required|string|max:255|unique:kpi_divisi,nama,'.$kpiDivisi->id.',id,division_id,'.$request->division_id.',bulan,'.$request->bulan.',tahun,'.$request->tahun,
            'tipe'        => 'required|in:kuantitatif,kualitatif,response,persentase',
            'satuan'      => 'nullable|string|max:50',
            'target'      => 'required|numeric',
            'bulan'       => 'required|integer|min:1|max:12',
            'tahun'       => 'required|integer|min:2000|max:2100',
        ]);

        DB::transaction(function () use ($kpiDivisi, $data) {
            $kpiDivisi->update($data + ['bobot'=>$kpiDivisi->bobot]); // bobot akan direset massal di bawah

            // 1) Reset AHP (bobot) untuk SEMUA KPI dalam periode & divisi yang sama
            KpiDivisi::where('division_id', $kpiDivisi->division_id)
                ->where('bulan', $kpiDivisi->bulan)
                ->where('tahun', $kpiDivisi->tahun)
                ->update(['bobot' => null]);

            // 2) Distribusi → stale
            KpiDivisiDistribution::where('division_id', $kpiDivisi->division_id)
                ->where('bulan', $kpiDivisi->bulan)
                ->where('tahun', $kpiDivisi->tahun)
                ->update(['status'=>'stale','hr_note'=>'Perlu input ulang: KPI Divisi berubah.']);

            // 3) Realisasi KUANTITATIF → stale & bersihkan item KPI ini
            $realQIds = KpiDivisiKuantitatifRealization::where('division_id',$kpiDivisi->division_id)
                ->where('bulan',$kpiDivisi->bulan)->where('tahun',$kpiDivisi->tahun)
                ->pluck('id');
            if ($realQIds->isNotEmpty()) {
                KpiDivisiKuantitatifRealization::whereIn('id',$realQIds)->update([
                    'status' => 'stale',
                    'hr_note'=> 'Perlu input ulang: KPI Divisi berubah.',
                    'total_score'=> null
                ]);
                KpiDivisiKuantitatifRealizationItem::whereIn('realization_id',$realQIds)
                    ->where('kpi_divisi_id',$kpiDivisi->id)->delete();
            }

            // 4) Realisasi KUALITATIF → stale & bersihkan item KPI ini
            $realKIds = KpiDivisiKualitatifRealization::where('division_id',$kpiDivisi->division_id)
                ->where('bulan',$kpiDivisi->bulan)->where('tahun',$kpiDivisi->tahun)
                ->pluck('id');
            if ($realKIds->isNotEmpty()) {
                KpiDivisiKualitatifRealization::whereIn('id',$realKIds)->update([
                    'status' => 'stale',
                    'hr_note'=> 'Perlu input ulang: KPI Divisi kualitatif berubah.',
                    'total_score'=> null
                ]);
                KpiDivisiKualitatifRealizationItem::whereIn('realization_id',$realKIds)
                    ->where('kpi_divisi_id',$kpiDivisi->id)->delete();
            }

            // 5) Realisasi RESPONSE → stale & bersihkan item KPI ini
            $realRIds = KpiDivisiResponseRealization::where('division_id', $kpiDivisi->division_id)
                ->where('bulan', $kpiDivisi->bulan)->where('tahun', $kpiDivisi->tahun)
                ->pluck('id');
            if ($realRIds->isNotEmpty()) {
                KpiDivisiResponseRealization::whereIn('id',$realRIds)->update([
                    'status'      => 'stale',
                    'hr_note'     => 'Perlu input ulang: KPI Divisi response berubah.',
                    'total_score' => null,
                ]);
                KpiDivisiResponseRealizationItem::whereIn('realization_id',$realRIds)
                    ->where('kpi_divisi_id',$kpiDivisi->id)->delete();
            }

            // 6) Realisasi PERSENTASE → stale (per KPI)
            if ($kpiDivisi->tipe === 'persentase') {
                KpiDivisiPersentaseRealization::where('kpi_divisi_id', $kpiDivisi->id)->update([
                    'status'  => 'stale',
                    'hr_note' => 'Perlu input ulang: KPI Divisi persentase berubah.',
                    'score'   => null,
                ]);
            }

            // 7) Bersihkan distribusi item KPI ini (kalau ada record distribusinya)
            KpiDivisiDistribution::where('division_id',$kpiDivisi->division_id)
                ->where('bulan',$kpiDivisi->bulan)->where('tahun',$kpiDivisi->tahun)
                ->each(function($dist) use ($kpiDivisi){
                    KpiDivisiDistributionItem::where('distribution_id',$dist->id)
                        ->where('kpi_divisi_id',$kpiDivisi->id)->delete();
                });
        });

        return redirect()->route('kpi-divisi.index')->with('success','KPI Divisi berhasil diperbarui.');
    }

    /** Hapus KPI Divisi (HR) */
    public function destroy(KpiDivisi $kpiDivisi)
    {
        DB::transaction(function () use ($kpiDivisi) {
            $snap = $kpiDivisi->replicate();
            $kpiDivisi->delete();

            // 1) Reset AHP (bobot) untuk SEMUA KPI dalam periode & divisi yang sama
            KpiDivisi::where('division_id', $snap->division_id)
                ->where('bulan', $snap->bulan)
                ->where('tahun', $snap->tahun)
                ->update(['bobot' => null]);

            // 2) Distribusi → stale
            KpiDivisiDistribution::where('division_id', $snap->division_id)
                ->where('bulan', $snap->bulan)
                ->where('tahun', $snap->tahun)
                ->update(['status'=>'stale','hr_note'=>'Perlu input ulang: KPI Divisi berubah.']);

            // 3) Realisasi KUANTITATIF → stale & bersihkan item KPI ini
            $realQIds = KpiDivisiKuantitatifRealization::where('division_id',$snap->division_id)
                ->where('bulan',$snap->bulan)->where('tahun',$snap->tahun)
                ->pluck('id');
            if ($realQIds->isNotEmpty()) {
                KpiDivisiKuantitatifRealization::whereIn('id',$realQIds)->update([
                    'status' => 'stale',
                    'hr_note'=> 'Perlu input ulang: KPI Divisi berubah.',
                    'total_score'=> null
                ]);
                KpiDivisiKuantitatifRealizationItem::whereIn('realization_id',$realQIds)
                    ->where('kpi_divisi_id',$snap->id)->delete();
            }

            // 4) Realisasi KUALITATIF → stale & bersihkan item KPI ini
            $realKIds = KpiDivisiKualitatifRealization::where('division_id',$snap->division_id)
                ->where('bulan',$snap->bulan)->where('tahun',$snap->tahun)
                ->pluck('id');
            if ($realKIds->isNotEmpty()) {
                KpiDivisiKualitatifRealization::whereIn('id',$realKIds)->update([
                    'status' => 'stale',
                    'hr_note'=> 'Perlu input ulang: KPI Divisi kualitatif berubah.',
                    'total_score'=> null
                ]);
                KpiDivisiKualitatifRealizationItem::whereIn('realization_id',$realKIds)
                    ->where('kpi_divisi_id',$snap->id)->delete();
            }

            // 5) Realisasi RESPONSE → stale & bersihkan item KPI ini
            $realRIds = KpiDivisiResponseRealization::where('division_id', $snap->division_id)
                ->where('bulan', $snap->bulan)->where('tahun', $snap->tahun)
                ->pluck('id');
            if ($realRIds->isNotEmpty()) {
                KpiDivisiResponseRealization::whereIn('id',$realRIds)->update([
                    'status'      => 'stale',
                    'hr_note'     => 'Perlu input ulang: KPI Divisi response berubah.',
                    'total_score' => null,
                ]);
                KpiDivisiResponseRealizationItem::whereIn('realization_id',$realRIds)
                    ->where('kpi_divisi_id',$snap->id)->delete();
            }

            // 6) Realisasi PERSENTASE → stale (per KPI)
            if ($snap->tipe === 'persentase') {
                KpiDivisiPersentaseRealization::where('kpi_divisi_id', $snap->id)->update([
                    'status'  => 'stale',
                    'hr_note' => 'Perlu input ulang: KPI Divisi persentase berubah.',
                    'score'   => null,
                ]);
            }

            // 7) Hapus distribusi item KPI ini (kalau ada record distribusinya)
            KpiDivisiDistribution::where('division_id',$snap->division_id)
                ->where('bulan',$snap->bulan)->where('tahun',$snap->tahun)
                ->each(function($dist) use ($snap){
                    KpiDivisiDistributionItem::where('distribution_id',$dist->id)
                        ->where('kpi_divisi_id',$snap->id)->delete();
                });
        });

        return redirect()->route('kpi-divisi.index')->with('success','KPI Divisi berhasil dihapus.');
    }
}
