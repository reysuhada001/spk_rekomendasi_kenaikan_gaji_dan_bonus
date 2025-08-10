<?php

namespace App\Http\Controllers;

use App\Models\KpiUmum;
use App\Models\KpiUmumRealization;
use App\Models\KpiUmumRealizationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KpiUmumController extends Controller
{
    public function __construct()
    {
        // index boleh owner/hr/leader
        $this->middleware('role:owner,hr,leader')->only('index');
        // CRUD khusus hr
        $this->middleware('role:hr')->except('index');
    }

    public function index(Request $request)
    {
        $search  = $request->input('search', '');
        $perPage = (int) $request->input('per_page', 10);

        $kpis = KpiUmum::when($search, function ($q) use ($search) {
                    $q->where(function($qq) use ($search){
                        $qq->where('nama','like',"%{$search}%")
                           ->orWhere('tipe','like',"%{$search}%")
                           ->orWhere('satuan','like',"%{$search}%")
                           ->orWhere('tahun','like',"%{$search}%");
                    });
                })
                ->orderByDesc('tahun')
                ->orderByDesc('bulan')
                ->orderBy('nama')
                ->paginate($perPage)
                ->appends(['search'=>$search,'per_page'=>$perPage]);

        $me = Auth::user();

        // daftar bulan untuk select di view
        $bulanList = [
            1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
            7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
        ];

        return view('kpi_umum.index', compact('kpis','search','perPage','bulanList','me'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama'   => 'required|string|max:255',
            'tipe'   => 'required|in:kuantitatif,kualitatif,response,persentase',
            'satuan' => 'nullable|string|max:50',
            'target' => 'required|numeric',
            'bulan'  => 'required|integer|min:1|max:12',
            'tahun'  => 'required|integer|min:2000|max:2100',
        ]);
        // bobot tidak diinput, akan direset ke 0
        $validated['bobot'] = null;

        DB::transaction(function() use ($validated) {
            KpiUmum::create($validated);
            // penambahan data → reset bobot periode terkait
            $this->invalidateWeightsAndRealizations((int)$validated['bulan'], (int)$validated['tahun']);
        });

        return redirect()->route('kpi-umum.index')
            ->with('success','KPI Umum berhasil ditambahkan. Bobot periode terkait direset ke 0 — silakan lakukan pembobotan ulang.');
    }

    public function update(Request $request, KpiUmum $kpi)
    {
        $validated = $request->validate([
            'nama'   => 'required|string|max:255',
            'tipe'   => 'required|in:kuantitatif,kualitatif,response,persentase',
            'satuan' => 'nullable|string|max:50',
            'target' => 'required|numeric',
            'bulan'  => 'required|integer|min:1|max:12',
            'tahun'  => 'required|integer|min:2000|max:2100',
        ]);

        DB::transaction(function() use ($kpi, $validated) {
            $oldBulan = (int)$kpi->bulan;
            $oldTahun = (int)$kpi->tahun;

            $kpi->update($validated);

            $newBulan = (int)$validated['bulan'];
            $newTahun = (int)$validated['tahun'];

            if ($oldBulan !== $newBulan || $oldTahun !== $newTahun) {
                $this->invalidateWeightsAndRealizations($oldBulan, $oldTahun);
                $this->invalidateWeightsAndRealizations($newBulan, $newTahun);
            }
        });

        return redirect()->route('kpi-umum.index')
            ->with('success','KPI Umum berhasil diperbarui.' . 
                (($request->bulan != $kpi->bulan || $request->tahun != $kpi->tahun) ? ' Bobot periode terkait direset ke 0 — silakan lakukan pembobotan ulang.' : ''));
    }


    public function destroy(KpiUmum $kpi)
    {
        DB::transaction(function() use ($kpi) {
            $bulan = (int)$kpi->bulan;
            $tahun = (int)$kpi->tahun;
            $kpi->delete();
            // pengurangan data → reset bobot periode terkait
            $this->invalidateWeightsAndRealizations($bulan, $tahun);
        });

        return redirect()->route('kpi-umum.index')
            ->with('success','KPI Umum berhasil dihapus. Bobot periode terkait direset ke 0 — silakan lakukan pembobotan ulang.');
    }

    private function invalidateWeightsAndRealizations(int $bulan, int $tahun): void
    {
        // 1) reset bobot KPI
        KpiUmum::where('bulan',$bulan)->where('tahun',$tahun)->update(['bobot'=>0]);

        // 2) invalidate semua realisasi periode tsb
        $reals = KpiUmumRealization::where('bulan',$bulan)->where('tahun',$tahun)->get();

        foreach ($reals as $r) {
            // hapus semua item realisasi agar pasti input ulang
            KpiUmumRealizationItem::where('realization_id',$r->id)->delete();

            // tandai stale + reset skor
            $r->update([
                'total_score' => 0,
                'status'      => 'stale',
                'hr_note'     => 'Perubahan data KPI. Mohon input ulang.'
            ]);
        }
    }
}