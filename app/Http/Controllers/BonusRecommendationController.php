<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\AhpGlobalWeight;
use App\Models\KpiUmumRealization;
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiPersentaseRealization;
use App\Models\PeerAssessment;
use App\Models\PeerAssessmentItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class BonusRecommendationController extends Controller
{
    private array $bulanList = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    // Ambang band bonus (longgar ringan)
    private float $BAIK_MAX = 115.0;
    private float $SANGAT_BAIK_START = 116.0;
    private float $SANGAT_BAIK_LINEAR_TO = 135.0;

    public function index(Request $request)
    {
        $me = Auth::user();

        $perPage = (int) $request->input('per_page', 10);
        $search  = $request->input('search', '');
        $bulan   = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun   = $request->filled('tahun') ? (int)$request->tahun : null;

        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;
        if ($me->role === 'leader')      $division_id = $me->division_id;
        elseif ($me->role === 'karyawan') $division_id = null;

        $divisions = Division::orderBy('name')->get();

        if (is_null($bulan) || is_null($tahun)) {
            $users = User::whereRaw('1=0')->paginate($perPage);
            return view('bonus-rekomendasi.index', [
                'me'=>$me,'users'=>$users,'bulan'=>$bulan,'tahun'=>$tahun,
                'division_id'=>$division_id,'divisions'=>$divisions,
                'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
                'rows'=>[],
            ]);
        }

        $usersQ = User::with('division')
            ->when($search, function($q) use($search){
                $q->where(function($qq) use($search){
                    $qq->where('full_name','like',"%{$search}%")
                       ->orWhere('nik','like',"%{$search}%")
                       ->orWhere('email','like',"%{$search}%")
                       ->orWhere('username','like',"%{$search}%");
                });
            });

        if     ($me->role === 'leader')   $usersQ->where('division_id',$me->division_id)->where('role','karyawan');
        elseif ($me->role === 'karyawan') $usersQ->where('id',$me->id);
        elseif (in_array($me->role,['owner','hr'],true)) {
            if (!empty($division_id)) $usersQ->where('division_id',$division_id);
            $usersQ->where('role','karyawan');
        }

        $users = $usersQ->orderBy('full_name')->paginate($perPage)->appends($request->all());

        [$wUmum, $wDivisi, $wPeer] = $this->getGlobalWeights();

        $rows = [];
        foreach ($users as $u) {
            $ku = $this->getKpiUmumScore($u->id, $bulan, $tahun);   // 0..200
            $kd = $this->getKpiDivisiScoreWeighted($u, $bulan, $tahun); // 0..200 (terkalikan bobot tipe)
            $kp = $this->getPeerScore($u->id, $bulan, $tahun);      // 0..100

            $weights = $this->renormalizeWeights(
                ['umum'=>$ku,'divisi'=>$kd,'peer'=>$kp],
                ['umum'=>$wUmum,'divisi'=>$wDivisi,'peer'=>$wPeer]
            );

            // Weighted sum murni
            $final = 0.0;
            if ($ku !== null) $final += $weights['umum']   * $ku;
            if ($kd !== null) $final += $weights['divisi'] * $kd;
            if ($kp !== null) $final += $weights['peer']   * $kp;
            $final = round($final, 2);

            [$label, $bonusPct] = $this->bonusBand($final);

            $rows[$u->id] = [
                'kpi_umum'=>$ku,'kpi_divisi'=>$kd,'peer'=>$kp,
                'w'=>$weights,'final'=>$final,'label'=>$label,'bonus_pct'=>$bonusPct
            ];
        }

        return view('bonus-rekomendasi.index', [
            'me'=>$me,'users'=>$users,'bulan'=>$bulan,'tahun'=>$tahun,
            'division_id'=>$division_id,'divisions'=>$divisions,
            'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
            'rows'=>$rows,
        ]);
    }

    /* =================== HELPERS =================== */

    private function getGlobalWeights(): array
    {
        $w = AhpGlobalWeight::latest()->first();
        if (!$w) return [1/3,1/3,1/3];
        $ku = (float)($w->w_kpi_umum ?? 0);
        $kd = (float)($w->w_kpi_divisi ?? 0);
        $kp = (float)($w->w_peer ?? 0);
        $sum = $ku+$kd+$kp;
        if ($sum <= 0) return [1/3,1/3,1/3];
        return [$ku/$sum, $kd/$sum, $kp/$sum];
    }

    private function getKpiUmumScore(int $userId, int $bulan, int $tahun): ?float
    {
        $r = KpiUmumRealization::where([
            'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
        ])->first();
        return $r ? round((float)$r->total_score, 2) : null;
    }

    /**
     * Bobot tipe per divisi (jumlah = 1).
     * Kalau kamu sudah punya tabel bobot tipe di DB, ganti fungsi ini supaya baca dari DB.
     */
    private function typeWeightsByDivision(?Division $div): array
    {
        $name = trim(strtolower($div->name ?? ''));

        // Technical Support Team
        if (str_contains($name,'technical') || str_contains($name,'support')) {
            return [
                'kuantitatif'=>0.5625,  // JD
                'kualitatif' =>0.0625,  // SAT
                'response'   =>0.1875,  // RT
                'persentase' =>0.1875,  // SLA
            ];
        }

        // Creatif Desain
        if (str_contains($name,'creatif') || str_contains($name,'creative') || str_contains($name,'desain')) {
            return [
                'kuantitatif'=>0.50,    // JPD
                'kualitatif' =>0.25,    // KHD
                'response'   =>0.125,   // PTW
                'persentase' =>0.125,   // RWP
            ];
        }

        // Default (misal Chat Sales Agent, jika belum ditentukan di DB)
        return [
            'kuantitatif'=>0.25,
            'kualitatif' =>0.25,
            'response'   =>0.25,
            'persentase' =>0.25,
        ];
    }

    /**
     * Kd berbobot:
     * - Jika kolom total_score tersedia (sudah berbobot di upstream), pakai nilainya apa adanya (tanpa dikali lagi).
     * - Jika hanya ada score mentah, ambil RATA-RATA di tiap kelompok tipe lalu kali bobot tipe.
     *   (Kalau 1 KPI per tipe, rata-rata = nilai itu sendiri).
     */
    private function getKpiDivisiScoreWeighted(User $user, int $bulan, int $tahun): ?float
    {
        $weights = $this->typeWeightsByDivision($user->division);

        $sum = 0.0;
        $any = false;

        // Kuantitatif (per-user)
        $q = KpiDivisiKuantitatifRealization::where([
                'user_id'=>$user->id,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->get();

        if ($q->isNotEmpty()) {
            $any = true;
            // cek apakah upstream sudah berbobot (punya total_score)
            if ($q->first()->getAttribute('total_score') !== null) {
                $sum += $q->sum('total_score'); // sudah berbobot -> tambahkan langsung
            } else {
                $avg = $q->avg('score') ?? 0;   // mentah -> rata-rata lalu kali bobot tipe
                $sum += $weights['kuantitatif'] * $avg;
            }
        }

        // Kualitatif (per-user)
        $k = KpiDivisiKualitatifRealization::where([
                'user_id'=>$user->id,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->get();

        if ($k->isNotEmpty()) {
            $any = true;
            if ($k->first()->getAttribute('total_score') !== null) {
                $sum += $k->sum('total_score');
            } else {
                $avg = $k->avg('score') ?? 0;
                $sum += $weights['kualitatif'] * $avg;
            }
        }

        // Response (per-user)
        $r = KpiDivisiResponseRealization::where([
                'user_id'=>$user->id,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->get();

        if ($r->isNotEmpty()) {
            $any = true;
            if ($r->first()->getAttribute('total_score') !== null) {
                $sum += $r->sum('total_score');
            } else {
                $avg = $r->avg('score') ?? 0;
                $sum += $weights['response'] * $avg;
            }
        }

        // Persentase (per-divisi; bisa >1 KPI)
        $p = KpiDivisiPersentaseRealization::where([
                'division_id'=>$user->division_id,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->get();

        if ($p->isNotEmpty()) {
            $any = true;
            if ($p->first()->getAttribute('total_score') !== null) {
                $sum += $p->sum('total_score'); // diasumsikan upstream sudah masukkan bobot
            } else {
                // Jika ada banyak KPI persentase, bagi bobot kelompok secara merata
                $avg = $p->avg('score') ?? 0;
                $sum += $weights['persentase'] * $avg;
            }
        }

        return $any ? round($sum, 2) : null;
    }

    private function getPeerScore(int $userId, int $bulan, int $tahun): ?float
    {
        $ids = PeerAssessment::where([
                'assessee_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,
            ])
            ->when(Schema::hasColumn('peer_assessments','status'), fn($q)=>$q->where('status','locked'))
            ->pluck('id');

        if ($ids->isEmpty()) return null;

        $scores = PeerAssessmentItem::whereIn('assessment_id',$ids)->pluck('score');
        if ($scores->isEmpty()) return null;

        return round($scores->avg() * 10.0, 2); // 1..10 -> 0..100
    }

    private function renormalizeWeights(array $values, array $weights): array
    {
        $sum = 0.0; $act = [];
        foreach ($values as $k=>$v) {
            if ($v !== null) { $act[$k] = $weights[$k]; $sum += $weights[$k]; }
            else            { $act[$k] = 0.0; }
        }
        if ($sum <= 0) return ['umum'=>1/3,'divisi'=>1/3,'peer'=>1/3];
        foreach ($act as $k=>$w) $act[$k] = $w / $sum;
        return $act;
    }

    private function bonusBand(float $finalScore): array
    {
        if ($finalScore <= 100.0) return ['Kurang', 0.0];

        if ($finalScore <= $this->BAIK_MAX) {
            $lo=101.0; $hi=$this->BAIK_MAX;
            $pct = 6.0 + (($finalScore-$lo)/max(1e-9,($hi-$lo))) * (9.0-6.0);
            return ['Baik', round(max(6.0,min(9.0,$pct)),2)];
        }

        $lo=$this->SANGAT_BAIK_START; $hi=$this->SANGAT_BAIK_LINEAR_TO;
        $base = max($lo, min($hi, $finalScore));
        $pct = 10.0 + (($base-$lo)/max(1e-9,($hi-$lo))) * (12.0-10.0);
        return ['Sangat Baik', round(max(10.0,min(12.0,$pct)),2)];
    }
}
