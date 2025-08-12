<?php

namespace App\Http\Controllers;

use App\Models\Aspek;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AspekController extends Controller
{
    public function __construct()
    {
        // index bisa diakses owner/hr/leader
        $this->middleware('role:owner,hr,leader')->only('index');
        // CRUD hanya HR
        $this->middleware('role:hr')->except('index');
    }

    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    public function index(Request $request)
    {
        $me      = Auth::user();
        $search  = trim($request->input('search',''));
        $perPage = (int) $request->input('per_page', 10);

        $aspeks = Aspek::when($search, function($q) use ($search){
                        $q->where(function($qq) use ($search){
                            $qq->where('nama','like',"%{$search}%")
                               ->orWhere('tahun','like',"%{$search}%");
                        });
                    })
                    ->orderBy('tahun','desc')
                    ->orderBy('bulan','desc')
                    ->orderBy('nama')
                    ->paginate($perPage)
                    ->appends($request->all());

        $bulanList = $this->bulanList;

        return view('aspek.index', compact('aspeks','bulanList','perPage','search','me'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'  => 'required|string|max:255',
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000|max:2100',
        ]);

        // pastikan unik per periode
        $exists = Aspek::where('nama',$data['nama'])
                    ->where('bulan',$data['bulan'])
                    ->where('tahun',$data['tahun'])
                    ->exists();
        if ($exists) {
            return back()->with('error','Aspek dengan periode tersebut sudah ada.')->withInput();
        }

        Aspek::create($data);

        return redirect()->route('aspek.index')->with('success','Aspek ditambahkan.');
    }

    public function update(Request $request, Aspek $aspek)
    {
        $data = $request->validate([
            'nama'  => 'required|string|max:255',
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000|max:2100',
        ]);

        $exists = Aspek::where('nama',$data['nama'])
                    ->where('bulan',$data['bulan'])
                    ->where('tahun',$data['tahun'])
                    ->where('id','<>',$aspek->id)
                    ->exists();
        if ($exists) {
            return back()->with('error','Aspek dengan periode tersebut sudah ada.')->withInput();
        }

        $aspek->update($data);

        return redirect()->route('aspek.index')->with('success','Aspek diperbarui.');
    }

    public function destroy(Aspek $aspek)
    {
        $aspek->delete();
        return redirect()->route('aspek.index')->with('success','Aspek dihapus.');
    }
}
