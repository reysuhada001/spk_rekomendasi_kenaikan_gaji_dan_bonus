<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\Aspek;
use App\Models\PeerAssessment;
use App\Models\PeerAssessmentItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeerAssessmentAdminController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    public function __construct()
    {
        $this->middleware(['auth','role:hr']);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search  = $request->input('search','');

        $bulan = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun = $request->filled('tahun') ? (int)$request->tahun : null;
        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;

        $divisions = Division::orderBy('name')->get();

        // Bila belum pilih bulan/tahun → kosong
        if (is_null($bulan) || is_null($tahun)) {
            $users = User::whereRaw('1=0')->paginate($perPage);
            return view('peer-assessments-admin.index', [
                'users'=>$users,'bulan'=>$bulan,'tahun'=>$tahun,'division_id'=>$division_id,
                'divisions'=>$divisions,'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
                'avgByUser'=>[],
            ]);
        }

        $usersQ = User::with('division')->where('role','karyawan')
            ->when($division_id, fn($q)=>$q->where('division_id',$division_id))
            ->when($search, function($q) use($search){
                $q->where(function($qq) use($search){
                    $qq->where('full_name','like',"%{$search}%")
                       ->orWhere('email','like',"%{$search}%")
                       ->orWhere('username','like',"%{$search}%");
                });
            })
            ->orderBy('full_name');

        $users = $usersQ->paginate($perPage)->appends($request->all());

        // Ambil rata-rata total per user (diterima) di periode
        $avgByUser = PeerAssessment::select('assessee_id',
                DB::raw('AVG(peer_assessment_items.score) as avg_score'))
            ->join('peer_assessment_items','peer_assessments.id','=','peer_assessment_items.assessment_id')
            ->where('peer_assessments.bulan',$bulan)
            ->where('peer_assessments.tahun',$tahun)
            ->groupBy('assessee_id')
            ->pluck('avg_score','assessee_id');

        return view('peer-assessments-admin.index', compact(
            'users','bulan','tahun','division_id','divisions','perPage','search'
        ) + ['bulanList'=>$this->bulanList,'avgByUser'=>$avgByUser]);
    }

    public function show(Request $request, $userId)
    {
        $bulan = $request->validate(['bulan'=>'required|integer|min:1|max:12'])['bulan'];
        $tahun = $request->validate(['tahun'=>'required|integer|min:2000|max:2100'])['tahun'];

        $user = User::with('division')->findOrFail($userId);

        $assessments = PeerAssessment::with(['assessor'])
            ->where('assessee_id',$user->id)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->orderBy('assessor_id')->get();

        $aspeks = Aspek::where('bulan',$bulan)->where('tahun',$tahun)->orderBy('nama')->get();
        $aspekIds = $aspeks->pluck('id')->all();

        // Map nilai: [assessment_id][aspek_id] = score
        $items = PeerAssessmentItem::whereIn('assessment_id',$assessments->pluck('id'))
            ->get()->groupBy('assessment_id');

        // Rata-rata per aspek & overall
        $sumPerAspek = array_fill_keys($aspekIds, 0);
        $countPerAspek = array_fill_keys($aspekIds, 0);
        $totalSum = 0; $totalCount = 0;

        foreach ($assessments as $a) {
            foreach (($items[$a->id] ?? collect()) as $it) {
                $sumPerAspek[$it->aspek_id] += (int)$it->score;
                $countPerAspek[$it->aspek_id] += 1;
                $totalSum += (int)$it->score;
                $totalCount += 1;
            }
        }

        $avgPerAspek = [];
        foreach ($aspekIds as $aid) {
            $avgPerAspek[$aid] = $countPerAspek[$aid] > 0 ? round($sumPerAspek[$aid] / $countPerAspek[$aid], 2) : null;
        }
        $avgTotal = $totalCount > 0 ? round($totalSum / $totalCount, 2) : null;

        return view('peer-assessments-admin.show', [
            'user'=>$user,'bulan'=>$bulan,'tahun'=>$tahun,
            'assessments'=>$assessments,'aspeks'=>$aspeks,'items'=>$items,
            'avgPerAspek'=>$avgPerAspek,'avgTotal'=>$avgTotal,
            'bulanList'=>$this->bulanList
        ]);
    }
}
