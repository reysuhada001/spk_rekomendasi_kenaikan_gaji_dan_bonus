<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Aspek;
use App\Models\PeerAssessment;
use App\Models\PeerAssessmentItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeerAssessmentController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    public function __construct()
    {
        $this->middleware(['auth','role:karyawan']);
    }

    public function index(Request $request)
    {
        $me = Auth::user();
        $perPage = (int) $request->input('per_page', 10);
        $search  = $request->input('search','');

        $bulan = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun = $request->filled('tahun') ? (int)$request->tahun : null;

        if (is_null($bulan) || is_null($tahun)) {
            $users = User::whereRaw('1=0')->paginate($perPage);
            return view('peer-assessments.index', [
                'me'=>$me,'users'=>$users,'bulan'=>$bulan,'tahun'=>$tahun,
                'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
                'assessedMap'=>[],
            ]);
        }

        $usersQ = User::with('division')
            ->where('division_id',$me->division_id)
            ->where('role','karyawan')
            ->where('id','<>',$me->id)
            ->when($search, function($q) use($search){
                $q->where(function($qq) use($search){
                    $qq->where('full_name','like',"%{$search}%")
                       ->orWhere('email','like',"%{$search}%")
                       ->orWhere('username','like',"%{$search}%");
                });
            })
            ->orderBy('full_name');

        $users = $usersQ->paginate($perPage)->appends($request->all());

        // map penilaian yang SUDAH ada
        $assessedMap = PeerAssessment::where('assessor_id',$me->id)
            ->where('bulan',$bulan)->where('tahun',$tahun)
            ->get()->keyBy('assessee_id');

        return view('peer-assessments.index', compact(
            'me','users','bulan','tahun','assessedMap','perPage','search'
        ) + ['bulanList'=>$this->bulanList]);
    }

    public function create(Request $request)
    {
        $me = Auth::user();

        $data = $request->validate([
            'assessee_id' => 'required|integer|exists:users,id',
            'bulan'       => 'required|integer|min:1|max:12',
            'tahun'       => 'required|integer|min:2000|max:2100',
        ]);

        $assessee = User::with('division')->findOrFail($data['assessee_id']);
        abort_unless($assessee->division_id === $me->division_id, 403);
        abort_unless($assessee->role === 'karyawan', 403);
        abort_if($assessee->id === $me->id, 403);

        $bulan = (int)$data['bulan']; $tahun = (int)$data['tahun'];

        // Jika sudah ada assessment, jangan boleh dibuka lagi
        $already = PeerAssessment::where([
            'assessor_id'=>$me->id, 'assessee_id'=>$assessee->id,
            'bulan'=>$bulan, 'tahun'=>$tahun,
        ])->exists();

        if ($already) {
            return redirect()->route('peer.index', ['bulan'=>$bulan,'tahun'=>$tahun])
                ->with('error','Anda sudah mengisi penilaian untuk rekan ini pada periode tersebut.');
        }

        $aspeks = Aspek::where('bulan',$bulan)->where('tahun',$tahun)
            ->orderBy('nama')->get();

        if ($aspeks->isEmpty()) {
            return redirect()->route('peer.index', ['bulan'=>$bulan,'tahun'=>$tahun])
                ->with('error','Belum ada aspek untuk periode tersebut.');
        }

        // tidak ada prefill (karena tidak boleh ubah setelah submit)
        $existing = [];
        $scale = $this->scaleOptions();

        return view('peer-assessments.create', compact(
            'assessee','bulan','tahun','aspeks','existing','scale'
        ) + ['bulanList'=>$this->bulanList]);
    }

    public function store(Request $request)
    {
        $me = Auth::user();

        $data = $request->validate([
            'assessee_id' => 'required|integer|exists:users,id',
            'bulan'       => 'required|integer|min:1|max:12',
            'tahun'       => 'required|integer|min:2000|max:2100',
            'score'       => 'required|array',
        ]);

        $assessee = User::findOrFail($data['assessee_id']);
        abort_unless($assessee->division_id === $me->division_id, 403);
        abort_unless($assessee->role === 'karyawan', 403);
        abort_if($assessee->id === $me->id, 403);

        $bulan = (int)$data['bulan']; $tahun = (int)$data['tahun'];

        // kalau sudah ada record, tolak (tidak overwrite)
        $exists = PeerAssessment::where([
            'assessor_id'=>$me->id, 'assessee_id'=>$assessee->id,
            'bulan'=>$bulan, 'tahun'=>$tahun,
        ])->first();
        if ($exists) {
            return redirect()->route('peer.index', ['bulan'=>$bulan,'tahun'=>$tahun])
                ->with('error','Penilaian sudah terkirim dan tidak dapat diubah.');
        }

        // Validasi aspek periode ini
        $aspekIds = Aspek::where('bulan',$bulan)->where('tahun',$tahun)->pluck('id')->toArray();
        if (empty($aspekIds)) return back()->with('error','Aspek periode ini belum ada.')->withInput();

        $rows = [];
        foreach ($data['score'] as $aspekId => $sc) {
            if (!in_array((int)$aspekId, $aspekIds, true)) continue;
            $val = (int)$sc;
            if ($val < 1 || $val > 10) return back()->with('error','Skor harus 1-10.')->withInput();
            $rows[] = ['aspek_id'=>(int)$aspekId, 'score'=>$val];
        }
        if (empty($rows)) return back()->with('error','Tidak ada skor yang dikirim.')->withInput();

        DB::transaction(function() use ($me,$assessee,$bulan,$tahun,$rows) {
            $assessment = PeerAssessment::create([
                'assessor_id' => $me->id,
                'assessee_id' => $assessee->id,
                'division_id' => $me->division_id,
                'bulan'       => $bulan,
                'tahun'       => $tahun,
                'submitted_at'=> now(),
            ]);
            $now = now();
            foreach ($rows as $r) {
                PeerAssessmentItem::create([
                    'assessment_id'=>$assessment->id,
                    'aspek_id'=>$r['aspek_id'],
                    'score'=>$r['score'],
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
        });

        return redirect()->route('peer.index', ['bulan'=>$bulan,'tahun'=>$tahun])
            ->with('success','Penilaian berhasil dikirim.');
    }

    private function scaleOptions(): array
    {
        return [
            1=>'1 - Sangat Kurang', 2=>'2 - Kurang',
            3=>'3 - Kurang Cukup',  4=>'4 - Mendekati Cukup',
            5=>'5 - Cukup',         6=>'6 - Cukup Baik',
            7=>'7 - Baik',          8=>'8 - Sangat Baik',
            9=>'9 - Istimewa',      10=>'10 - Luar Biasa',
        ];
    }
}
