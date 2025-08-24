<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\Aspek;
use App\Models\PeerAssessment;
use App\Models\PeerAssessmentItem;
use Illuminate\Http\Request;
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

        $avgQuery = PeerAssessment::select('peer_assessments.assessee_id',
                DB::raw('AVG(peer_assessment_items.score) as avg_score'))
            ->join('peer_assessment_items','peer_assessments.id','=','peer_assessment_items.assessment_id')
            ->join('users as assessee','assessee.id','=','peer_assessments.assessee_id')
            ->where('peer_assessments.bulan',$bulan)
            ->where('peer_assessments.tahun',$tahun);

        if (!empty($division_id)) {
            $avgQuery->where('assessee.division_id', $division_id);
        }

        $avgByUser = $avgQuery
            ->groupBy('peer_assessments.assessee_id')
            ->pluck('avg_score','peer_assessments.assessee_id');

        return view('peer-assessments-admin.index', compact(
            'users','bulan','tahun','division_id','divisions','perPage','search'
        ) + ['bulanList'=>$this->bulanList,'avgByUser'=>$avgByUser]);
    }

    public function show(Request $request, $userId)
    {
        $validated = $request->validate([
            'bulan'=>'required|integer|min:1|max:12',
            'tahun'=>'required|integer|min:2000|max:2100'
        ]);
        $bulan = (int)$validated['bulan'];
        $tahun = (int)$validated['tahun'];

        $user = User::with('division')->findOrFail($userId);

        $assessments = PeerAssessment::with(['assessor'])
            ->where('assessee_id',$user->id)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->orderBy('assessor_id')
            ->get();

        // Normalizer untuk nama aspek → key stabil
        $norm = function (?string $s): string {
            $s = preg_replace('/\s+/', ' ', trim($s ?? ''));
            return strtolower($s);
        };

        // Item + nama aspeknya
        $items = PeerAssessmentItem::whereIn('assessment_id', $assessments->pluck('id'))
            ->with('aspek:id,nama')
            ->get();

        // Matrix skor per assessment menggunakan key nama
        $itemsMatrix = [];            // [assessment_id][keyNama] = score
        $nameByKey   = [];            // [keyNama] => Label tampilan
        foreach ($items as $it) {
            $label = $it->aspek->nama ?? ('Aspek #'.$it->aspek_id);
            $key   = $norm($label);
            $itemsMatrix[$it->assessment_id][$key] = (int) $it->score;
            if (!isset($nameByKey[$key])) $nameByKey[$key] = $label;
        }

        // Aspek yang didefinisikan pada periode ini → diprioritaskan urutannya
        $periodAspeks = Aspek::where('bulan',$bulan)->where('tahun',$tahun)->orderBy('nama')->get();

        // Susun kolom: mulai dari aspek periode (urut), lalu tambahkan yang belum ada dari item
        $columns = [];  // [['key'=>..., 'label'=>...], ...]
        $seen = [];
        foreach ($periodAspeks as $a) {
            $k = $norm($a->nama);
            $columns[] = ['key'=>$k, 'label'=>$a->nama];
            $seen[$k] = true;
            // samakan label resmi
            $nameByKey[$k] = $a->nama;
        }
        foreach ($nameByKey as $k => $label) {
            if (!isset($seen[$k])) {
                $columns[] = ['key'=>$k, 'label'=>$label];
                $seen[$k] = true;
            }
        }

        // Agregasi rata-rata
        $sumPer = []; $cntPer = [];
        $totalSum = 0; $totalCnt = 0;
        foreach ($assessments as $a) {
            $row = $itemsMatrix[$a->id] ?? [];
            foreach ($row as $k => $score) {
                if (!isset($sumPer[$k])) { $sumPer[$k]=0; $cntPer[$k]=0; }
                $sumPer[$k] += (int)$score;  $cntPer[$k] += 1;
                $totalSum   += (int)$score;  $totalCnt   += 1;
            }
        }

        $avgPerKey = [];
        foreach ($columns as $c) {
            $k = $c['key'];
            $avgPerKey[$k] = (!empty($cntPer[$k])) ? round($sumPer[$k] / $cntPer[$k], 2) : null;
        }
        $avgTotal = $totalCnt > 0 ? round($totalSum / $totalCnt, 2) : null;

        return view('peer-assessments-admin.show', [
            'user'        => $user,
            'bulan'       => $bulan,
            'tahun'       => $tahun,
            'assessments' => $assessments,
            'columns'     => $columns,
            'itemsMatrix' => $itemsMatrix,
            'avgPerKey'   => $avgPerKey,
            'avgTotal'    => $avgTotal,
            'bulanList'   => $this->bulanList,
        ]);
    }
}
