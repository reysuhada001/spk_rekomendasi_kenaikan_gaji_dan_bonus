<?php

namespace App\Http\Controllers;

use App\Models\AhpGlobalWeight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AhpGlobalController extends Controller
{
    public function __construct()
    {
        // owner/hr/leader bisa lihat; hanya HR yang bisa simpan
        $this->middleware('role:owner,hr,leader')->only(['index']);
        $this->middleware('role:hr')->only(['hitung']);
    }

    // Skala Saaty 1..9 (tanpa opsi kebalikan; kebalikan dihitung otomatis)
    private array $saatyOptions = [
        '1' => 'Sama penting (1)',
        '2' => 'Sedikit lebih penting (2)',
        '3' => 'Lebih penting (3)',
        '4' => 'Antara sedang (4)',
        '5' => 'Penting kuat (5)',
        '6' => 'Antara kuat (6)',
        '7' => 'Sangat penting (7)',
        '8' => 'Antara sangat & ekstrem (8)',
        '9' => 'Ekstrem penting (9)',
    ];

    public function index()
    {
        $criteria = [
            'kpi_umum'   => 'KPI Umum',
            'kpi_divisi' => 'KPI Divisi',
            'peer'       => 'Penilaian Karyawan (Peer)',
        ];

        // Ambil bobot tersimpan (jika ada)
        $existing = AhpGlobalWeight::query()->orderBy('id', 'desc')->first();

        /**
         * Urutan pair di UI DISESUAIKAN agar kiri > kanan mengikuti
         * hierarki: KPI Divisi > KPI Umum > Peer
         * Sehingga user cukup memilih 1..9 (tanpa nilai kebalikan).
         */
        $pairs = [
            ['kpi_divisi', 'kpi_umum'],   // Divisi lebih penting dari Umum
            ['kpi_umum',   'peer'],       // Umum lebih penting dari Peer
            ['kpi_divisi', 'peer'],       // Divisi lebih penting dari Peer
        ];

        return view('ahp-global.index', [
            'criteria'     => $criteria,
            'pairs'        => $pairs,
            'saatyOptions' => $this->saatyOptions,
            'existing'     => $existing,
            'me'           => Auth::user(),
        ]);
    }

    public function hitung(Request $request)
    {
        // Validasi sesuai name di view (urutan pair disesuaikan)
        $validated = $request->validate([
            'pair_kpi_divisi_kpi_umum' => 'required|in:1,2,3,4,5,6,7,8,9',
            'pair_kpi_umum_peer'       => 'required|in:1,2,3,4,5,6,7,8,9',
            'pair_kpi_divisi_peer'     => 'required|in:1,2,3,4,5,6,7,8,9',
        ]);

        // index kriteria
        $idx = ['kpi_umum' => 0, 'kpi_divisi' => 1, 'peer' => 2];
        $n = 3;

        // Matriks perbandingan 3x3 (diagonal 1)
        $A = array_fill(0, $n, array_fill(0, $n, 1.0));

        // Helper ambil nilai float
        $f = fn ($k) => (float) $validated[$k];

        // --- mapping pair ke matriks ---
        // pair: (kiri > kanan), input 1..9, sisi sebaliknya otomatis 1/nilai
        // 1) KPI Divisi vs KPI Umum
        $A[$idx['kpi_divisi']][$idx['kpi_umum']] = $f('pair_kpi_divisi_kpi_umum');
        $A[$idx['kpi_umum']][$idx['kpi_divisi']] = 1.0 / $A[$idx['kpi_divisi']][$idx['kpi_umum']];

        // 2) KPI Umum vs Peer
        $A[$idx['kpi_umum']][$idx['peer']] = $f('pair_kpi_umum_peer');
        $A[$idx['peer']][$idx['kpi_umum']] = 1.0 / $A[$idx['kpi_umum']][$idx['peer']];

        // 3) KPI Divisi vs Peer
        $A[$idx['kpi_divisi']][$idx['peer']] = $f('pair_kpi_divisi_peer');
        $A[$idx['peer']][$idx['kpi_divisi']] = 1.0 / $A[$idx['kpi_divisi']][$idx['peer']];

        // --- Perhitungan AHP ---
        // 1) jumlah kolom
        $colSum = array_fill(0, $n, 0.0);
        for ($j = 0; $j < $n; $j++) {
            for ($i = 0; $i < $n; $i++) $colSum[$j] += $A[$i][$j];
        }

        // 2) normalisasi kolom & eigen approx (rata-rata baris)
        $norm = array_fill(0, $n, array_fill(0, $n, 0.0));
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $norm[$i][$j] = $A[$i][$j] / ($colSum[$j] ?: 1e-12);
            }
        }
        $w = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $row = 0.0;
            for ($j = 0; $j < $n; $j++) $row += $norm[$i][$j];
            $w[$i] = $row / $n;
        }

        // 3) lambda max
        $Aw = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) $Aw[$i] += $A[$i][$j] * $w[$j];
        }
        $lambdaVals = [];
        for ($i = 0; $i < $n; $i++) $lambdaVals[] = $Aw[$i] / ($w[$i] ?: 1e-12);
        $lambdaMax = array_sum($lambdaVals) / $n;

        // 4) CI & CR (RI untuk n=3 = 0.58)
        $CI = ($n > 1) ? (($lambdaMax - $n) / ($n - 1)) : 0.0;
        $RI = 0.58;
        $CR = $RI > 0 ? ($CI / $RI) : 0.0;

        if ($CR > 0.1) {
            return back()
                ->with('error', 'Pembobotan gagal: CR = ' . round($CR, 4) . ' > 0.1. Mohon sesuaikan perbandingan.')
                ->withInput();
        }

        // normalisasi w agar jumlah = 1 (secara teori sudah 1, tapi diamankan)
        $sumW = array_sum($w) ?: 1.0;
        $W_umum   = $w[$idx['kpi_umum']]   / $sumW;
        $W_divisi = $w[$idx['kpi_divisi']] / $sumW;
        $W_peer   = $w[$idx['peer']]       / $sumW;

        // Simpan (update baris terakhir jika ada; else create)
        $existing = AhpGlobalWeight::query()->orderBy('id', 'desc')->first();
        if ($existing) {
            $existing->update([
                'w_kpi_umum'   => $W_umum,
                'w_kpi_divisi' => $W_divisi,
                'w_peer'       => $W_peer,
                'lambda_max'   => $lambdaMax,
                'ci'           => $CI,
                'cr'           => $CR,
            ]);
        } else {
            AhpGlobalWeight::create([
                'w_kpi_umum'   => $W_umum,
                'w_kpi_divisi' => $W_divisi,
                'w_peer'       => $W_peer,
                'lambda_max'   => $lambdaMax,
                'ci'           => $CI,
                'cr'           => $CR,
            ]);
        }

        return redirect()->route('ahp.global.index')->with('success', 'Bobot AHP Global berhasil disimpan. CR=' . round($CR, 4));
    }
}
