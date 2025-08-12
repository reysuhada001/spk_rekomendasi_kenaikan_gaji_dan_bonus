<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;

// AHP Global (bobot 3 kriteria)
use App\Models\AhpGlobalWeight;

// KPI Umum
use App\Models\KpiUmumRealization;

// KPI Divisi — header realisasi per tipe
use App\Models\KpiDivisiKuantitatifRealization;
use App\Models\KpiDivisiKualitatifRealization;
use App\Models\KpiDivisiResponseRealization;
use App\Models\KpiDivisiPersentaseRealization;

// Peer assessment
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

    public function index(Request $request)
    {
        $me = Auth::user();

        $perPage = (int) $request->input('per_page', 10);
        $search  = $request->input('search', '');

        $bulan = $request->filled('bulan') ? (int)$request->bulan : null;
        $tahun = $request->filled('tahun') ? (int)$request->tahun : null;

        // Divisi filter (HR/Owner saja)
        $division_id = $request->filled('division_id') ? (int)$request->division_id : null;
        if ($me->role === 'leader') {
            $division_id = $me->division_id;
        } elseif ($me->role === 'karyawan') {
            $division_id = null; // tidak digunakan (hanya dirinya)
        }

        $divisions = Division::orderBy('name')->get();

        // Jika leader/karyawan: wajib bulan & tahun. Jika owner/hr: wajib bulan, tahun, dan (opsional) divisi? —
        // untuk kenyamanan, jika divisi kosong tetap tampil semua.
        if (is_null($bulan) || is_null($tahun)) {
            $users = User::whereRaw('1=0')->paginate($perPage);
            return view('bonus-rekomendasi.index', [
                'me'=>$me,'users'=>$users,'bulan'=>$bulan,'tahun'=>$tahun,
                'division_id'=>$division_id,'divisions'=>$divisions,
                'bulanList'=>$this->bulanList,'perPage'=>$perPage,'search'=>$search,
                'rows'=>[],
            ]);
        }

        // Base users sesuai role
        $usersQ = User::with('division')
            ->when($search, function($q) use ($search) {
                $q->where(function($qq) use ($search){
                    $qq->where('full_name','like',"%{$search}%")
                       ->orWhere('nik','like',"%{$search}%")
                       ->orWhere('email','like',"%{$search}%")
                       ->orWhere('username','like',"%{$search}%");
                });
            });

        if ($me->role === 'leader') {
            $usersQ->where('division_id', $me->division_id)->where('role','karyawan');
        } elseif ($me->role === 'karyawan') {
            $usersQ->where('id', $me->id);
        } elseif (in_array($me->role, ['owner','hr'], true)) {
            if (!empty($division_id)) $usersQ->where('division_id', $division_id);
            $usersQ->where('role','karyawan');
        }

        $users = $usersQ->orderBy('full_name')->paginate($perPage)->appends($request->all());

        // Bobot global (AHP); fallback 1/3 jika belum ada
        [$wUmum, $wDivisi, $wPeer] = $this->getGlobalWeights();

        $rows = [];
        foreach ($users as $u) {
            $ku = $this->getKpiUmumScore($u->id, $bulan, $tahun);     // 0..∞ (umumnya ~% di sekitar 100)
            $kd = $this->getKpiDivisiScore($u->id, $bulan, $tahun);   // 0..∞ (rata 4 tipe tersedia)
            $kp = $this->getPeerScore($u->id, $bulan, $tahun);        // 0..100

            // Normalisasi bobot jika ada komponen yang tidak tersedia (null)
            $weights = $this->renormalizeWeights(
                ['umum'=>$ku, 'divisi'=>$kd, 'peer'=>$kp],
                ['umum'=>$wUmum, 'divisi'=>$wDivisi, 'peer'=>$wPeer]
            );

            $final = 0.0;
            if ($ku !== null) $final += $weights['umum']  * $ku;
            if ($kd !== null) $final += $weights['divisi']* $kd;
            if ($kp !== null) $final += $weights['peer']  * $kp;
            $final = round($final, 2);

            [$label, $bonusPct] = $this->bonusBand($final);

            $rows[$u->id] = [
                'kpi_umum'   => $ku,
                'kpi_divisi' => $kd,
                'peer'       => $kp,
                'w'          => $weights,
                'final'      => $final,
                'label'      => $label,
                'bonus_pct'  => $bonusPct,
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

    /** Bobot global (AHP). Fallback = sama rata (1/3). */
    private function getGlobalWeights(): array
    {
        $w = AhpGlobalWeight::latest()->first();
        if (!$w) return [1/3, 1/3, 1/3];

        $ku = (float)($w->kpi_umum ?? 0);
        $kd = (float)($w->kpi_divisi ?? 0);
        $kp = (float)($w->peer     ?? 0);
        $sum = $ku + $kd + $kp;
        if ($sum <= 0) return [1/3, 1/3, 1/3];

        return [$ku/$sum, $kd/$sum, $kp/$sum];
    }

    /** KPI Umum — ambil skor approved (total_score) per user/periode. */
    private function getKpiUmumScore(int $userId, int $bulan, int $tahun): ?float
    {
        $r = KpiUmumRealization::where([
                'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();

        return $r ? round((float)$r->total_score, 2) : null;
    }

    /**
     * KPI Divisi — gabungan 4 tipe (kuantitatif, kualitatif, response, persentase).
     *  - 3 tipe pertama: per-user (header realisasi).
     *  - persentase: per-divisi (BUKAN per-user!) → ambil berdasarkan division_id karyawan.
     * Nilai akhir = rata-rata dari tipe yang tersedia (tanpa bobot tipe).
     */
    private function getKpiDivisiScore(int $userId, int $bulan, int $tahun): ?float
    {
        $scores = [];
        // kuantitatif
        $q = KpiDivisiKuantitatifRealization::where([
                'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
        if ($q) $scores[] = (float)$q->total_score;

        // kualitatif
        $k = KpiDivisiKualitatifRealization::where([
                'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
        if ($k) $scores[] = (float)$k->total_score;

        // response
        $r = KpiDivisiResponseRealization::where([
                'user_id'=>$userId,'bulan'=>$bulan,'tahun'=>$tahun,'status'=>'approved'
            ])->first();
        if ($r) $scores[] = (float)$r->total_score;

        // persentase — PER DIVISI
        $user = User::find($userId);
        if ($user && $user->division_id) {
            $p = KpiDivisiPersentaseRealization::where([
                    'division_id'=>$user->division_id,
                    'bulan'=>$bulan,
                    'tahun'=>$tahun,
                    'status'=>'approved',
                ])->first();

            if ($p) $scores[] = (float)$p->total_score;
        }

        if (empty($scores)) return null;

        $avg = array_sum($scores) / count($scores);
        return round($avg, 2);
    }

    /**
     * Peer score — rata-rata skor (1..10) → dikonversi ke 0..100.
     * Sumber skor diambil dari tabel items (peer_assessment_items) yang
     * berelasi dengan assessment milik “assessee” (yang dinilai).
     */
    private function getPeerScore(int $userId, int $bulan, int $tahun): ?float
    {
        // Ambil semua assessment id di mana YANG DINILAI = $userId
        $assessmentIds = PeerAssessment::where([
                'assessee_id' => $userId,
                'bulan'       => $bulan,
                'tahun'       => $tahun,
            ])
            // jika kamu pakai status 'locked' untuk yang final, aktifkan baris ini:
            ->when(Schema::hasColumn('peer_assessments','status'), function($q){
                $q->where('status','locked');
            })
            ->pluck('id');

        if ($assessmentIds->isEmpty()) return null;

        // Ambil semua skor item untuk assessment-assessment tersebut
        $scores = PeerAssessmentItem::whereIn('assessment_id', $assessmentIds)->pluck('score');
        if ($scores->isEmpty()) return null;

        $avg10 = $scores->avg();       // skala 1..10
        $avg100 = $avg10 * 10.0;       // 0..100
        return round($avg100, 2);
    }

    /** Renormalisasi bobot jika ada komponen yang missing (null). */
    private function renormalizeWeights(array $values, array $weights): array
    {
        // $values: ['umum'=>?float, 'divisi'=>?float, 'peer'=>?float]
        // $weights: bobot awal (0..1)
        $sum = 0.0;
        $active = [];
        foreach ($values as $k => $v) {
            if ($v !== null) {
                $active[$k] = $weights[$k];
                $sum += $weights[$k];
            } else {
                $active[$k] = 0.0;
            }
        }
        if ($sum <= 0) {
            // semua null → pakai rata sama
            return ['umum'=>1/3,'divisi'=>1/3,'peer'=>1/3];
        }

        foreach ($active as $k => $w) {
            $active[$k] = $w / $sum;
        }
        return $active;
    }

    /** Band bonus sesuai skor final. */
    private function bonusBand(float $finalScore): array
    {
        if ($finalScore <= 100.0) {
            return ['Kurang', 0.0];
        }

        if ($finalScore <= 120.0) {
            // 101..120 -> 6..9% (linear)
            $pct = 6.0 + ( ($finalScore - 101.0) / (120.0 - 101.0) ) * (9.0 - 6.0);
            return ['Baik', round(max(6.0, min(9.0, $pct)), 2)];
        }

        // >120 -> 10..12% (linear, cap 12)
        // Ambil referensi 121..140 untuk skala; di atas 140 tetap 12
        $base = max(121.0, min(140.0, $finalScore));
        $pct  = 10.0 + ( ($base - 121.0) / (140.0 - 121.0) ) * (12.0 - 10.0);
        return ['Sangat Baik', round(max(10.0, min(12.0, $pct)), 2)];
    }
}
