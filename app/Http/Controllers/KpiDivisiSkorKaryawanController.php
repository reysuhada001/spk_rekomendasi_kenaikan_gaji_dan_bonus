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

        // Hak akses & filter dasar
        if ($me->role === 'leader') {
            $division_id = $me->division_id;
        } elseif ($me->role === 'karyawan') {
            // hanya dirinya; division filter diabaikan
        } elseif (!in_array($me->role, ['owner','hr','leader','karyawan'], true)) {
            abort(403);
        }

        $divisions = Division::orderBy('name')->get();

        // Jika belum pilih periode -> kosong
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

        // Query user (role karyawan)
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

        $usersQ->where('role','karyawan');

        $users = $usersQ->orderBy('full_name')
            ->paginate($perPage)
            ->appends($request->all());

        // Cache bobot KPI per (division, bulan, tahun) agar efisien
        $weightCache = []; // [$divId][$bulan][$tahun][$kpi_id] = bobot_kpi_asli

        $rows = [];

        foreach ($users as $u) {
            $divId = $u->division_id;

            // Ambil bobot KPI untuk divisi & periode ini (sekali per kombinasi)
            $key = $divId.'-'.$bulan.'-'.$tahun;
            if (!isset($weightCache[$key])) {
                $kpis = KpiDivisi::where('division_id', $divId)
                    ->where('bulan',$bulan)->where('tahun',$tahun)->get();

                $map = [];
                foreach ($kpis as $kpi) {
                    $map[$kpi->id] = (float)($kpi->bobot ?? 0); // langsung bobot AHP as-is (tanpa normalisasi)
                }
                $weightCache[$key] = $map;
            }
            $wKpi = $weightCache[$key];

            // ------------- KUANTITATIF -------------
            $scoreQ = null;
            $qHdr = KpiDivisiKuantitatifRealization::where([
                'user_id'=>$u->id,'division_id'=>$divId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();

            if ($qHdr) {
                $items = KpiDivisiKuantitatifRealizationItem::where('realization_id',$qHdr->id)->get();
                $sum = 0.0; $has = false;
                foreach ($items as $it) {
                    $w = $wKpi[$it->kpi_divisi_id] ?? 0.0;               // bobot KPI (as-is)
                    $sc = (float)($it->score ?? 0);                       // skor raw indikator (0..100/150)
                    if ($w > 0) { $sum += $w * $sc; $has = true; }
                }
                if ($has) $scoreQ = round($sum, 2);
            }

            // ------------- KUALITATIF -------------
            $scoreK = null;
            $kHdr = KpiDivisiKualitatifRealization::where([
                'user_id'=>$u->id,'division_id'=>$divId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();

            if ($kHdr) {
                $items = KpiDivisiKualitatifRealizationItem::where('realization_id',$kHdr->id)->get();
                $sum = 0.0; $has = false;
                foreach ($items as $it) {
                    $w = $wKpi[$it->kpi_divisi_id] ?? 0.0;
                    $sc = (float)($it->score ?? 0);
                    if ($w > 0) { $sum += $w * $sc; $has = true; }
                }
                if ($has) $scoreK = round($sum, 2);
            }

            // ------------- RESPONSE -------------
            $scoreR = null;
            $rHdr = KpiDivisiResponseRealization::where([
                'user_id'=>$u->id,'division_id'=>$divId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();

            if ($rHdr) {
                $items = KpiDivisiResponseRealizationItem::where('realization_id',$rHdr->id)->get();
                $sum = 0.0; $has = false;
                foreach ($items as $it) {
                    $w = $wKpi[$it->kpi_divisi_id] ?? 0.0;
                    $sc = (float)($it->score ?? 0);
                    if ($w > 0) { $sum += $w * $sc; $has = true; }
                }
                if ($has) $scoreR = round($sum, 2);
            }

            // ------------- PERSENTASE (per KPI, bukan per user) -------------
            $scoreP = null;
            $persReal = KpiDivisiPersentaseRealization::where('division_id',$divId)
                ->where('bulan',$bulan)->where('tahun',$tahun)
                ->where('status','approved')
                ->get()
                ->keyBy('kpi_divisi_id');

            if ($persReal->count() > 0) {
                $sum = 0.0; $has = false;
                foreach ($persReal as $kpiId => $row) {
                    $w = $wKpi[$kpiId] ?? 0.0;
                    $sc = (float)($row->score ?? 0);
                    if ($w > 0) { $sum += $w * $sc; $has = true; }
                }
                if ($has) $scoreP = round($sum, 2);
            }

            // ------------- TOTAL (jumlah penjumlahan terbobot lintas semua KPI) -------------
            // Karena tiap score tipe sudah = Σ(score_kpi × bobot_kpi) untuk tipe itu,
            // maka total = penjumlahan ke-4 tipe tersebut (yang ada).
            $parts = array_values(array_filter([$scoreQ,$scoreK,$scoreR,$scoreP], fn($v)=> $v !== null));
            $total = count($parts) ? round(array_sum($parts), 2) : null;

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
