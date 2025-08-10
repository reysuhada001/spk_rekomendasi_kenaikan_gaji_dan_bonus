<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KpiDivisi;
use App\Models\Division;
use Illuminate\Support\Facades\DB;

class AhpKpiDivisiController extends Controller
{
    // Skala Saaty (1–9 saja)
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
        $bulan = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun = $request->filled('tahun') ? (int)$request->tahun : null;
        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;

        $divisions = Division::orderBy('name')->get();

        if (is_null($bulan) || is_null($tahun) || is_null($division_id)) {
            $kpis = collect(); $pairs = [];
        } else {
            $kpis = KpiDivisi::where('division_id',$division_id)
                ->where('bulan',$bulan)->where('tahun',$tahun)
                ->orderBy('nama')->get();

            $pairs = [];
            for ($i=0;$i<$kpis->count();$i++){
                for($j=$i+1;$j<$kpis->count();$j++){
                    $pairs[] = [$kpis[$i],$kpis[$j]];
                }
            }
        }

        return view('ahp-kpi-divisi.index', compact(
            'bulan','tahun','division_id','divisions','kpis','pairs'
        ) + ['bulanList'=>$this->bulanList,'saatyOptions'=>$this->saatyOptions]);
    }

    public function hitung(Request $request)
    {
        $data = $request->validate([
            'division_id' => 'required|exists:divisions,id',
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000|max:2100',
        ]);

        $division_id = (int)$data['division_id'];
        $bulan = (int)$data['bulan'];
        $tahun = (int)$data['tahun'];

        $kpis = KpiDivisi::where('division_id',$division_id)->where('bulan',$bulan)->where('tahun',$tahun)
            ->orderBy('nama')->get();
        $n = $kpis->count();
        if ($n < 2) return back()->with('error','Minimal 2 KPI Divisi pada periode tersebut.')->withInput();

        // hanya 1..9 → parse float biasa
        $parse = fn(string $s): float => (float)$s;

        $A = array_fill(0,$n,array_fill(0,$n,1.0));
        $idIndex=[]; foreach($kpis as $i=>$k) $idIndex[$k->id] = $i;

        foreach($request->all() as $k=>$v){
            if(preg_match('/^pair_(\d+)_(\d+)$/',$k,$m)){
                $i = $idIndex[(int)$m[1]] ?? null;
                $j = $idIndex[(int)$m[2]] ?? null;
                if($i===null || $j===null) continue;
                $val = $parse((string)$v);
                if($val<=0) return back()->with('error','Skala tidak valid.')->withInput();
                $A[$i][$j] = $val;
                $A[$j][$i] = 1.0 / $val; // kebalikan otomatis
            }
        }
        // validasi terisi
        for($i=0;$i<$n;$i++){ for($j=$i+1;$j<$n;$j++){
            if(!isset($A[$i][$j]) || $A[$i][$j]<=0) return back()->with('error','Masih ada pasangan kosong.')->withInput();
        }}

        // hitung
        $colSum=array_fill(0,$n,0.0);
        for($j=0;$j<$n;$j++){ $s=0.0; for($i=0;$i<$n;$i++) $s+=$A[$i][$j]; $colSum[$j]=$s; }
        $norm=array_fill(0,$n,array_fill(0,$n,0.0));
        for($i=0;$i<$n;$i++){ for($j=0;$j<$n;$j++){ $norm[$i][$j]=$A[$i][$j]/($colSum[$j]?:1e-12); } }
        $w=array_fill(0,$n,0.0);
        for($i=0;$i<$n;$i++){ $sr=0.0; for($j=0;$j<$n;$j++) $sr+=$norm[$i][$j]; $w[$i]=$sr/$n; }
        $Aw=array_fill(0,$n,0.0);
        for($i=0;$i<$n;$i++){ $s=0.0; for($j=0;$j<$n;$j++) $s+=$A[$i][$j]*$w[$j]; $Aw[$i]=$s; }
        $lambdaVals=[]; for($i=0;$i<$n;$i++) $lambdaVals[]=$Aw[$i]/($w[$i]?:1e-12);
        $lambdaMax=array_sum($lambdaVals)/$n;

        $CI = ($n>1) ? (($lambdaMax-$n)/($n-1)) : 0.0;
        $RI_table=[1=>0,2=>0,3=>0.58,4=>0.90,5=>1.12,6=>1.24,7=>1.32,8=>1.41,9=>1.45,10=>1.49];
        $RI=$RI_table[$n] ?? 1.49;
        $CR = $RI>0 ? ($CI/$RI) : 0.0;

        if ($CR > 0.1) {
            return back()->with('error','Pembobotan gagal: CR='.round($CR,4).' > 0.1.')->withInput();
        }

        DB::transaction(function() use ($kpis,$w){
            foreach($kpis as $i=>$k){ $k->update(['bobot'=>$w[$i]]); }
        });

        return redirect()->route('kpi-divisi.index', ['division_id'=>$division_id,'bulan'=>$bulan,'tahun'=>$tahun])
            ->with('success','Bobot AHP KPI Divisi tersimpan. CR='.round($CR,4));
    }
}
