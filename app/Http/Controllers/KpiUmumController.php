<?php

namespace App\Http\Controllers;

use App\Models\KpiUmum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // bobot tidak diterima dari input → null/default
        $validated['bobot'] = null;

        KpiUmum::create($validated);
        return redirect()->route('kpi-umum.index')->with('success','KPI Umum berhasil ditambahkan.');
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

        // bobot tetap tidak bisa diubah dari form CRUD
        $kpi->update($validated);
        return redirect()->route('kpi-umum.index')->with('success','KPI Umum berhasil diperbarui.');
    }

    public function destroy(KpiUmum $kpi)
    {
        $kpi->delete();
        return redirect()->route('kpi-umum.index')->with('success','KPI Umum berhasil dihapus.');
    }
}