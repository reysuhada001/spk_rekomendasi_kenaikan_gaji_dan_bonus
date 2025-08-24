<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use App\Models\KpiDivisi;
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKuantitatifRealizationItem;
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiKualitatifRealizationItem;
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiResponseRealizationItem;
use App\Models\KpiDivisiPersentaseRealization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KpiDivisiSkorDivisiController extends Controller
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

        // Semua role boleh akses; dropdown semua divisi untuk filter
        $divisionsAll = Division::orderBy('name')->get();

        // Jika belum pilih periode -> kosong
        if (is_null($bulan) || is_null($tahun)) {
            $divisions = Division::whereRaw('1=0')->paginate($perPage);
            return view('kpi-divisi-skor-divisi.index', [
                'me'          => $me,
                'divisions'   => $divisions,
                'divisionsAll'=> $divisionsAll,
                'bulan'       => $bulan,
                'tahun'       => $tahun,
                'division_id' => $division_id,
                'bulanList'   => $this->bulanList,
                'perPage'     => $perPage,
                'search'      => $search,
                'rows'        => [],
            ]);
        }

        // Query divisi sesuai filter
        $divQ = Division::query();
        if (!empty($division_id)) $divQ->where('id', $division_id);
        if ($search !== '')       $divQ->where('name', 'like', "%{$search}%");
        $divisions = $divQ->orderBy('name')->paginate($perPage)->appends($request->all());

        $rows = [];

        foreach ($divisions as $div) {
            $divisionId = $div->id;

            // Ambil semua KPI divisi (semua tipe) untuk periode ini
            $allKpis = KpiDivisi::where('division_id', $divisionId)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->get();

            // Peta bobot KPI (as-is). Jika semua 0/null, fallback rata.
            $kpiWeight = [];
            foreach ($allKpis as $kpi) {
                $kpiWeight[$kpi->id] = (float)($kpi->bobot ?? 0);
            }
            $totalW = array_sum($kpiWeight);
            if ($totalW <= 0 && count($kpiWeight) > 0) {
                $eq = 1.0 / count($kpiWeight);
                foreach ($kpiWeight as $id => $_) $kpiWeight[$id] = $eq;
            }

            // Kelompok KPI per tipe (untuk memudahkan)
            $kpisByType = [
                'kuantitatif' => $allKpis->where('tipe','kuantitatif')->pluck('id')->all(),
                'kualitatif'  => $allKpis->where('tipe','kualitatif' )->pluck('id')->all(),
                'response'    => $allKpis->where('tipe','response'   )->pluck('id')->all(),
                'persentase'  => $allKpis->where('tipe','persentase' )->pluck('id')->all(),
            ];

            // Ambil semua karyawan divisi ini (yang berperan sebagai karyawan)
            $userIds = User::where('division_id', $divisionId)
                ->where('role','karyawan')
                ->pluck('id')->all();

            // ============ KUANTITATIF (Σ w_kpi * avg_user_score_per_kpi) ============
            $scoreQ = null;
            if (!empty($userIds) && !empty($kpisByType['kuantitatif'])) {
                $qHdr = KpiDivisiKuantitatifRealization::where('division_id', $divisionId)
                    ->where('bulan', $bulan)->where('tahun', $tahun)
                    ->where('status', 'approved')
                    ->whereIn('user_id', $userIds)
                    ->get();
                if ($qHdr->isNotEmpty()) {
                    $qItems = KpiDivisiKuantitatifRealizationItem::whereIn('realization_id', $qHdr->pluck('id'))
                        ->get();

                    // Kumpulkan skor per KPI → rata-rata per KPI
                    $agg = []; // [kpi_id => ['sum'=>..., 'n'=>...]]
                    foreach ($qItems as $it) {
                        $kpiId = (int)$it->kpi_divisi_id;
                        if (!in_array($kpiId, $kpisByType['kuantitatif'], true)) continue;
                        $sc = (float)($it->score ?? 0);
                        if (!isset($agg[$kpiId])) $agg[$kpiId] = ['sum'=>0.0,'n'=>0];
                        $agg[$kpiId]['sum'] += $sc;
                        $agg[$kpiId]['n']   += 1;
                    }

                    $sumWeighted = 0.0; $has = false;
                    foreach ($agg as $kpiId => $st) {
                        if ($st['n'] <= 0) continue;
                        $avg = $st['sum'] / $st['n'];
                        $w   = $kpiWeight[$kpiId] ?? 0.0;
                        if ($w > 0) { $sumWeighted += $w * $avg; $has = true; }
                    }
                    if ($has) $scoreQ = round($sumWeighted, 2);
                }
            }

            // ============ KUALITATIF ============
            $scoreK = null;
            if (!empty($userIds) && !empty($kpisByType['kualitatif'])) {
                $kHdr = KpiDivisiKualitatifRealization::where('division_id', $divisionId)
                    ->where('bulan', $bulan)->where('tahun', $tahun)
                    ->where('status', 'approved')
                    ->whereIn('user_id', $userIds)
                    ->get();
                if ($kHdr->isNotEmpty()) {
                    $kItems = KpiDivisiKualitatifRealizationItem::whereIn('realization_id', $kHdr->pluck('id'))
                        ->get();

                    $agg = [];
                    foreach ($kItems as $it) {
                        $kpiId = (int)$it->kpi_divisi_id;
                        if (!in_array($kpiId, $kpisByType['kualitatif'], true)) continue;
                        $sc = (float)($it->score ?? 0);
                        if (!isset($agg[$kpiId])) $agg[$kpiId] = ['sum'=>0.0,'n'=>0];
                        $agg[$kpiId]['sum'] += $sc;
                        $agg[$kpiId]['n']   += 1;
                    }

                    $sumWeighted = 0.0; $has = false;
                    foreach ($agg as $kpiId => $st) {
                        if ($st['n'] <= 0) continue;
                        $avg = $st['sum'] / $st['n'];
                        $w   = $kpiWeight[$kpiId] ?? 0.0;
                        if ($w > 0) { $sumWeighted += $w * $avg; $has = true; }
                    }
                    if ($has) $scoreK = round($sumWeighted, 2);
                }
            }

            // ============ RESPONSE ============
            $scoreR = null;
            if (!empty($userIds) && !empty($kpisByType['response'])) {
                $rHdr = KpiDivisiResponseRealization::where('division_id', $divisionId)
                    ->where('bulan', $bulan)->where('tahun', $tahun)
                    ->where('status', 'approved')
                    ->whereIn('user_id', $userIds)
                    ->get();
                if ($rHdr->isNotEmpty()) {
                    $rItems = KpiDivisiResponseRealizationItem::whereIn('realization_id', $rHdr->pluck('id'))
                        ->get();

                    $agg = [];
                    foreach ($rItems as $it) {
                        $kpiId = (int)$it->kpi_divisi_id;
                        if (!in_array($kpiId, $kpisByType['response'], true)) continue;
                        $sc = (float)($it->score ?? 0);
                        if (!isset($agg[$kpiId])) $agg[$kpiId] = ['sum'=>0.0,'n'=>0];
                        $agg[$kpiId]['sum'] += $sc;
                        $agg[$kpiId]['n']   += 1;
                    }

                    $sumWeighted = 0.0; $has = false;
                    foreach ($agg as $kpiId => $st) {
                        if ($st['n'] <= 0) continue;
                        $avg = $st['sum'] / $st['n'];
                        $w   = $kpiWeight[$kpiId] ?? 0.0;
                        if ($w > 0) { $sumWeighted += $w * $avg; $has = true; }
                    }
                    if ($has) $scoreR = round($sumWeighted, 2);
                }
            }

            // ============ PERSENTASE (per KPI, bukan per user) ============
            $scoreP = null;
            if (!empty($kpisByType['persentase'])) {
                $persReal = KpiDivisiPersentaseRealization::whereIn('kpi_divisi_id', $kpisByType['persentase'])
                    ->where('division_id', $divisionId)
                    ->where('bulan', $bulan)
                    ->where('tahun', $tahun)
                    ->where('status', 'approved')
                    ->get()
                    ->keyBy('kpi_divisi_id');

                if ($persReal->isNotEmpty()) {
                    $sumWeighted = 0.0; $has = false;
                    foreach ($persReal as $kpiId => $row) {
                        $w = $kpiWeight[$kpiId] ?? 0.0;
                        $sc = (float)($row->score ?? 0);
                        if ($w > 0) { $sumWeighted += $w * $sc; $has = true; }
                    }
                    if ($has) $scoreP = round($sumWeighted, 2);
                }
            }

            // ======== TOTAL = Σ (w_kpi × skor_kpi) lintas semua tipe ========
            // Karena skor per-tipe sudah Σ(w_kpi × avg_score_kpi), total = jumlah keempat tipe.
            $total = null;
            $parts = array_filter([$scoreQ, $scoreK, $scoreR, $scoreP], fn($v) => $v !== null);
            if (!empty($parts)) $total = round(array_sum($parts), 2);

            $rows[$divisionId] = [
                'q'     => $scoreQ,
                'k'     => $scoreK,
                'r'     => $scoreR,
                'p'     => $scoreP,
                'total' => $total,
            ];
        }

        return view('kpi-divisi-skor-divisi.index', [
            'me'           => $me,
            'divisions'    => $divisions,
            'divisionsAll' => $divisionsAll,
            'bulan'        => $bulan,
            'tahun'        => $tahun,
            'division_id'  => $division_id,
            'bulanList'    => $this->bulanList,
            'perPage'      => $perPage,
            'search'       => $search,
            'rows'         => $rows,
        ]);
    }
}
