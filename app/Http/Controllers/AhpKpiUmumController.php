<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KpiUmum;
use Illuminate\Support\Facades\DB;

class AhpKpiUmumController extends Controller
{
    // Skala Saaty deskriptif (kedua arah, termasuk resiprokal)
    private array $saatyOptions = [
        '1' => 'Sama penting (1)',
        '2' => 'Sedikit lebih penting (2)',
        '3' => 'Lebih penting (3)',
        '4' => 'Antara sedikit & kuat (4)',
        '5' => 'Lebih penting kuat (5)',
        '6' => 'Antara kuat & sangat (6)',
        '7' => 'Sangat lebih penting (7)',
        '8' => 'Antara sangat & sangat-sangat (8)',
        '9' => 'Sangat-sangat lebih penting (9)',
    ];

    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    public function index(Request $request)
    {
        // Bulan & tahun boleh kosong (null)
        $bulan = $request->filled('bulan') ? (int)$request->input('bulan') : null;
        $tahun = $request->filled('tahun') ? (int)$request->input('tahun') : null;

        // List bulan untuk dropdown
        $bulanList = [
            1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
            7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
        ];

        // List tahun sederhana (mis. Y-5 .. Y+5). Bisa Anda ganti ke DISTINCT dari DB bila perlu.
        $currentY = (int)date('Y');
        $tahunList = range($currentY - 5, $currentY + 5);

        // Jika salah satu belum dipilih → kosongkan data
        if (is_null($bulan) || is_null($tahun)) {
            $kpis = collect();
            $pairs = [];
        } else {
            $kpis = \App\Models\KpiUmum::where('bulan', $bulan)->where('tahun', $tahun)
                ->orderBy('nama')->get();

            $pairs = [];
            for ($i=0; $i<$kpis->count(); $i++) {
                for ($j=$i+1; $j<$kpis->count(); $j++) {
                    $pairs[] = [$kpis[$i], $kpis[$j]];
                }
            }
        }

        return view('ahp-kpi-umum.index', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'bulanList' => $bulanList,
            'tahunList' => $tahunList,
            'kpis' => $kpis,
            'pairs' => $pairs,
            'saatyOptions' => $this->saatyOptions,
        ]);
    }


    public function hitung(Request $request)
    {
        $validated = $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000|max:2100',
        ]);

        $bulan = (int)$validated['bulan'];
        $tahun = (int)$validated['tahun'];

        $kpis = KpiUmum::where('bulan', $bulan)->where('tahun', $tahun)
            ->orderBy('nama')->get();

        $n = $kpis->count();
        if ($n < 2) {
            return back()->with('error', 'Minimal 2 KPI pada bulan & tahun tersebut.')->withInput();
        }

        // Parse nilai dropdown menjadi float (termasuk 1/3 → 0.333..)
        $parse = function(string $s): float {
            if (str_contains($s, '/')) {
                [$a,$b] = explode('/',$s,2);
                $a = (float)$a; $b = (float)$b;
                return $b != 0 ? $a/$b : 0.0;
            }
            return (float)$s;
        };

        // Siapkan matriks perbandingan NxN (diagonal 1, simetris resiprokal)
        $A = array_fill(0, $n, array_fill(0, $n, 1.0));

        // Ambil semua pasangan i<j dari request: name="pair_{id1}_{id2}"
        // dan isi A[i][j] sesuai KPI urutan
        $idIndex = []; // map id → index
        foreach ($kpis as $idx => $k) $idIndex[$k->id] = $idx;

        foreach ($request->all() as $key => $val) {
            if (preg_match('/^pair_(\d+)_(\d+)$/', $key, $m)) {
                $id1 = (int)$m[1]; $id2 = (int)$m[2];
                if (!isset($idIndex[$id1], $idIndex[$id2])) continue;

                $i = $idIndex[$id1]; $j = $idIndex[$id2];
                $v = $parse((string)$val);
                if ($v <= 0) {
                    return back()->with('error', 'Skala tidak valid pada beberapa pasangan.')->withInput();
                }
                $A[$i][$j] = $v;
                $A[$j][$i] = 1.0 / $v;
            }
        }

        // Validasi: semua pasangan terisi
        for ($i=0; $i<$n; $i++) {
            for ($j=$i+1; $j<$n; $j++) {
                if (!isset($A[$i][$j]) || $A[$i][$j] <= 0) {
                    return back()->with('error', 'Masih ada pasangan yang belum dipilih.')->withInput();
                }
            }
        }

        // --- Perhitungan AHP ---
        // 1) Jumlah kolom
        $colSum = array_fill(0, $n, 0.0);
        for ($j=0; $j<$n; $j++) {
            $s = 0.0;
            for ($i=0; $i<$n; $i++) $s += $A[$i][$j];
            $colSum[$j] = $s;
        }

        // 2) Normalisasi kolom & rata-rata baris → eigenvector (bobot)
        $norm = array_fill(0, $n, array_fill(0, $n, 0.0));
        for ($i=0; $i<$n; $i++) {
            for ($j=0; $j<$n; $j++) {
                $norm[$i][$j] = $A[$i][$j] / ($colSum[$j] ?: 1e-12);
            }
        }
        $w = array_fill(0, $n, 0.0);
        for ($i=0; $i<$n; $i++) {
            $sumRow = 0.0;
            for ($j=0; $j<$n; $j++) $sumRow += $norm[$i][$j];
            $w[$i] = $sumRow / $n;
        }

        // 3) Hitung λ_max via (A*w)/w → rata-rata
        $Aw = array_fill(0, $n, 0.0);
        for ($i=0; $i<$n; $i++) {
            $s = 0.0;
            for ($j=0; $j<$n; $j++) $s += $A[$i][$j] * $w[$j];
            $Aw[$i] = $s;
        }
        $lambdaVals = [];
        for ($i=0; $i<$n; $i++) $lambdaVals[] = $Aw[$i] / ($w[$i] ?: 1e-12);
        $lambdaMax = array_sum($lambdaVals) / $n;

        // 4) CI & CR
        $CI = ($n > 1) ? (($lambdaMax - $n) / ($n - 1)) : 0.0;
        $RI_table = [
            1=>0.00, 2=>0.00, 3=>0.58, 4=>0.90, 5=>1.12,
            6=>1.24, 7=>1.32, 8=>1.41, 9=>1.45, 10=>1.49
        ];
        $RI = $RI_table[$n] ?? 1.49;
        $CR = $RI > 0 ? ($CI / $RI) : 0.0;

        // 5) Cek konsistensi
        if ($CR > 0.1) {
            return back()->with('error', 'Pembobotan gagal: Consistency Ratio (CR) = '.round($CR,4).' > 0.1. Mohon sesuaikan perbandingan.');
        }

        // 6) Simpan bobot ke kpi_umum.bobot (transaksi)
        DB::transaction(function() use ($kpis, $w) {
            foreach ($kpis as $idx => $kpi) {
                $kpi->update(['bobot' => $w[$idx]]);
            }
        });

        return redirect()->route('kpi-umum.index', ['per_page'=>request('per_page'), 'search'=>request('search')])
            ->with('success', 'Bobot AHP berhasil dihitung & disimpan. CR='.round($CR,4));
    }
}