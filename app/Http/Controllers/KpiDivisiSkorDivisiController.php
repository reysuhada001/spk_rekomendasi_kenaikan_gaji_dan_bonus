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

        // Semua role boleh akses & melihat semua divisi (opsional bisa difilter)
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

        // Query divisi sesuai filter pencarian/divisi
        $divQ = Division::query();
        if (!empty($division_id)) {
            $divQ->where('id', $division_id);
        }
        if ($search !== '') {
            $divQ->where('name', 'like', "%{$search}%");
        }

        $divisions = $divQ->orderBy('name')->paginate($perPage)->appends($request->all());

        $rows = [];
        foreach ($divisions as $div) {
            $divisionId = $div->id;

            $allKpis = KpiDivisi::where('division_id', $divisionId)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->get();

            $kpisByType = [
                'kuantitatif' => $allKpis->where('tipe','kuantitatif')->values(),
                'kualitatif'  => $allKpis->where('tipe','kualitatif')->values(),
                'response'    => $allKpis->where('tipe','response')->values(),
                'persentase'  => $allKpis->where('tipe','persentase')->values(),
            ];

            // Normalisasi bobot AHP DI DALAM tiap tipe (fallback rata jika bobot kosong)
            $kpiWeightInType = [];
            foreach ($kpisByType as $t => $rowsKpi) {
                $sum = (float) $rowsKpi->sum(fn($k)=> (float)($k->bobot ?? 0));
                if ($sum > 0) {
                    foreach ($rowsKpi as $kpi) {
                        $kpiWeightInType[$t][$kpi->id] = ((float)$kpi->bobot) / $sum;
                    }
                } else {
                    $n = $rowsKpi->count();
                    if ($n > 0) {
                        $eq = 1.0 / $n;
                        foreach ($rowsKpi as $kpi) {
                            $kpiWeightInType[$t][$kpi->id] = $eq;
                        }
                    }
                }
            }

            // Ambil karyawan divisi ini
            $userIds = User::where('division_id', $divisionId)
                ->where('role','karyawan')
                ->pluck('id')
                ->all();

            // ========= KUANTITATIF =========
            $qHdr = KpiDivisiKuantitatifRealization::where('division_id', $divisionId)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->where('status', 'approved')
                ->whereIn('user_id', $userIds)
                ->get()
                ->keyBy('user_id');

            $qItems = $qHdr->isNotEmpty()
                ? KpiDivisiKuantitatifRealizationItem::whereIn('realization_id', $qHdr->pluck('id'))
                    ->get()
                    ->groupBy('realization_id')
                : [];

            $perUserQ = [];
            foreach ($qHdr as $uid => $hdr) {
                $its = $qItems[$hdr->id] ?? collect();
                if ($its->isEmpty()) continue;

                $sum = 0.0; $has = false;
                foreach ($its as $it) {
                    $w = $kpiWeightInType['kuantitatif'][$it->kpi_divisi_id] ?? null;
                    if ($w === null) continue;
                    $sum += $w * (float) ($it->score ?? 0);
                    $has = true;
                }
                if ($has) $perUserQ[] = $sum;
            }
            $scoreQ = count($perUserQ) ? round(array_sum($perUserQ) / count($perUserQ), 2) : null;

            // ========= KUALITATIF =========
            $kHdr = KpiDivisiKualitatifRealization::where('division_id', $divisionId)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->where('status', 'approved')
                ->whereIn('user_id', $userIds)
                ->get()
                ->keyBy('user_id');

            $kItems = $kHdr->isNotEmpty()
                ? KpiDivisiKualitatifRealizationItem::whereIn('realization_id', $kHdr->pluck('id'))
                    ->get()
                    ->groupBy('realization_id')
                : [];

            $perUserK = [];
            foreach ($kHdr as $uid => $hdr) {
                $its = $kItems[$hdr->id] ?? collect();
                if ($its->isEmpty()) continue;

                $sum = 0.0; $has = false;
                foreach ($its as $it) {
                    $w = $kpiWeightInType['kualitatif'][$it->kpi_divisi_id] ?? null;
                    if ($w === null) continue;
                    $sum += $w * (float) ($it->score ?? 0);
                    $has = true;
                }
                if ($has) $perUserK[] = $sum;
            }
            $scoreK = count($perUserK) ? round(array_sum($perUserK) / count($perUserK), 2) : null;

            // ========= RESPONSE =========
            $rHdr = KpiDivisiResponseRealization::where('division_id', $divisionId)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->where('status', 'approved')
                ->whereIn('user_id', $userIds)
                ->get()
                ->keyBy('user_id');

            $rItems = $rHdr->isNotEmpty()
                ? KpiDivisiResponseRealizationItem::whereIn('realization_id', $rHdr->pluck('id'))
                    ->get()
                    ->groupBy('realization_id')
                : [];

            $perUserR = [];
            foreach ($rHdr as $uid => $hdr) {
                $its = $rItems[$hdr->id] ?? collect();
                if ($its->isEmpty()) continue;

                $sum = 0.0; $has = false;
                foreach ($its as $it) {
                    $w = $kpiWeightInType['response'][$it->kpi_divisi_id] ?? null;
                    if ($w === null) continue;
                    $sum += $w * (float) ($it->score ?? 0);
                    $has = true;
                }
                if ($has) $perUserR[] = $sum;
            }
            $scoreR = count($perUserR) ? round(array_sum($perUserR) / count($perUserR), 2) : null;

            // ========= PERSENTASE (per KPI, approved) =========
            $scoreP = null;
            if ($kpisByType['persentase']->count() > 0) {
                $persIds  = $kpisByType['persentase']->pluck('id');
                $persReal = KpiDivisiPersentaseRealization::whereIn('kpi_divisi_id', $persIds)
                    ->where('division_id', $divisionId)
                    ->where('bulan', $bulan)
                    ->where('tahun', $tahun)
                    ->where('status', 'approved')
                    ->get()
                    ->keyBy('kpi_divisi_id');

                if ($persReal->count() > 0) {
                    $sum = 0.0; $has = false;
                    foreach ($persReal as $kpiId => $row) {
                        $w = $kpiWeightInType['persentase'][$kpiId] ?? null;
                        if ($w === null) continue;
                        $sum += $w * (float) ($row->score ?? 0);
                        $has = true;
                    }
                    if ($has) $scoreP = round($sum, 2);
                }
            }

            // Skor akhir per divisi = rata-rata sederhana dari tipe yang ADA
            $parts = array_values(array_filter([$scoreQ, $scoreK, $scoreR, $scoreP], fn($v) => $v !== null));
            $total = count($parts) ? round(array_sum($parts) / count($parts), 2) : null;

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
