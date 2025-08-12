<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use App\Models\KpiDivisi;

// Realisasi KUANTITATIF
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKuantitatifRealizationItem;

// Realisasi KUALITATIF
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiKualitatifRealizationItem;

// Realisasi RESPONSE
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiResponseRealizationItem;

// Realisasi PERSENTASE (per KPI, bukan per user)
use App\Models\KpiDivisiPersentaseRealization;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KpiDivisiSkorKaryawanController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    public function index(Request $request)
    {
        $me = Auth::user();

        $perPage     = (int) $request->input('per_page', 10);
        $search      = trim((string)$request->input('search', ''));
        $bulan       = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun       = $request->filled('tahun') ? (int)$request->tahun : null;
        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;

        // Hak akses & filter
        if ($me->role === 'leader') {
            $division_id = $me->division_id;
        } elseif ($me->role === 'karyawan') {
            // hanya dirinya; division filter diabaikan
        } elseif (!in_array($me->role, ['owner','hr','leader','karyawan'], true)) {
            abort(403);
        }

        $divisions = Division::orderBy('name')->get();

        // Jika belum pilih periode, kosongkan data
        if (is_null($bulan) || is_null($tahun)) {
            $users = User::whereRaw('1=0')->paginate($perPage);
            return view('kpi-divisi-skor-karyawan.index', [
                'me'=>$me,'users'=>$users,
                'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$division_id,
                'divisions'=>$divisions,'bulanList'=>$this->bulanList,
                'perPage'=>$perPage,'search'=>$search,
                'rows'=>[],
            ]);
        }

        // Base query user
        $usersQ = User::with('division')
            ->when($search !== '', function($q) use ($search) {
                $q->where(function($qq) use ($search){
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
        $usersQ->where('role','karyawan');

        $users = $usersQ->orderBy('full_name')->paginate($perPage)->appends($request->all());

        // Siapkan skor per user
        $rows = [];

        // KPI per tipe di division masing-masing user
        foreach ($users as $u) {
            $divId = $u->division_id;

            // ambil semua KPI divisi periode ini utk divisi user
            $allKpis = KpiDivisi::where('division_id', $divId)
                ->where('bulan',$bulan)->where('tahun',$tahun)->get();

            $kpisByType = [
                'kuantitatif' => $allKpis->where('tipe','kuantitatif')->values(),
                'kualitatif'  => $allKpis->where('tipe','kualitatif')->values(),
                'response'    => $allKpis->where('tipe','response')->values(),
                'persentase'  => $allKpis->where('tipe','persentase')->values(),
            ];

            // Normalisasi bobot AHP di dalam tipe (jika tersedia); kalau tidak ada bobot, rata per KPI di tipe
            $wInType = [];
            foreach ($kpisByType as $t => $ks) {
                $sum = (float)$ks->sum(fn($k)=> (float)($k->bobot ?? 0));
                if ($sum > 0) {
                    foreach ($ks as $kpi) $wInType[$t][$kpi->id] = ((float)$kpi->bobot) / $sum;
                } else {
                    $n = $ks->count();
                    if ($n > 0) { $eq = 1.0 / $n; foreach ($ks as $kpi) $wInType[$t][$kpi->id] = $eq; }
                }
            }

            // KUANTITATIF (approved)
            $qHdr = KpiDivisiKuantitatifRealization::where([
                'user_id'=>$u->id,'division_id'=>$divId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
            $scoreQ = null;
            if ($qHdr) {
                $items = KpiDivisiKuantitatifRealizationItem::where('realization_id',$qHdr->id)->get();
                $sum = 0.0; $has = false;
                foreach ($items as $it) {
                    $w = $wInType['kuantitatif'][$it->kpi_divisi_id] ?? null;
                    if ($w === null) continue;
                    $sum += $w * (float)($it->score ?? 0);
                    $has = true;
                }
                if ($has) $scoreQ = round($sum,2);
            }

            // KUALITATIF
            $kHdr = KpiDivisiKualitatifRealization::where([
                'user_id'=>$u->id,'division_id'=>$divId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
            $scoreK = null;
            if ($kHdr) {
                $items = KpiDivisiKualitatifRealizationItem::where('realization_id',$kHdr->id)->get();
                $sum = 0.0; $has = false;
                foreach ($items as $it) {
                    $w = $wInType['kualitatif'][$it->kpi_divisi_id] ?? null;
                    if ($w === null) continue;
                    $sum += $w * (float)($it->score ?? 0);
                    $has = true;
                }
                if ($has) $scoreK = round($sum,2);
            }

            // RESPONSE
            $rHdr = KpiDivisiResponseRealization::where([
                'user_id'=>$u->id,'division_id'=>$divId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
            $scoreR = null;
            if ($rHdr) {
                $items = KpiDivisiResponseRealizationItem::where('realization_id',$rHdr->id)->get();
                $sum = 0.0; $has = false;
                foreach ($items as $it) {
                    $w = $wInType['response'][$it->kpi_divisi_id] ?? null;
                    if ($w === null) continue;
                    $sum += $w * (float)($it->score ?? 0);
                    $has = true;
                }
                if ($has) $scoreR = round($sum,2);
            }

            // PERSENTASE (per KPI, bukan per user) — skor sama utk semua user di divisi ini
            $scoreP = null;
            if ($kpisByType['persentase']->count() > 0) {
                $persIds = $kpisByType['persentase']->pluck('id');
                $persReal = KpiDivisiPersentaseRealization::whereIn('kpi_divisi_id',$persIds)
                    ->where('division_id',$divId)->where('bulan',$bulan)->where('tahun',$tahun)
                    ->where('status','approved')->get()->keyBy('kpi_divisi_id');

                if ($persReal->count() > 0) {
                    $sum = 0.0; $has = false;
                    foreach ($persReal as $kpiId => $row) {
                        $w = $wInType['persentase'][$kpiId] ?? null;
                        if ($w === null) continue;
                        $sum += $w * (float)($row->score ?? 0);
                        $has = true;
                    }
                    if ($has) $scoreP = round($sum,2);
                }
            }

            // === Skor akhir = rata-rata sederhana dari tipe yang ADA (tanpa bobot antar tipe) ===
            $parts = array_values(array_filter([$scoreQ,$scoreK,$scoreR,$scoreP], fn($v)=> $v !== null));
            $total = count($parts) ? round(array_sum($parts) / count($parts), 2) : null;

            $rows[$u->id] = [
                'q'=>$scoreQ,'k'=>$scoreK,'r'=>$scoreR,'p'=>$scoreP,'total'=>$total
            ];
        }

        return view('kpi-divisi-skor-karyawan.index', [
            'me'=>$me, 'users'=>$users,
            'bulan'=>$bulan, 'tahun'=>$tahun, 'division_id'=>$division_id,
            'divisions'=>$divisions, 'bulanList'=>$this->bulanList,
            'perPage'=>$perPage, 'search'=>$search,
            'rows'=>$rows,
        ]);
    }
}
