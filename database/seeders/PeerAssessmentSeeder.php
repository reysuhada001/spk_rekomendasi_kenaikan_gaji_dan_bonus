<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Division;
use App\Models\PeerAssessment;
use App\Models\PeerAssessmentItem;

class PeerAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil 3 divisi target
        $divisions = Division::whereIn('name', [
            'Technical Support Team',
            'Chat Sales Agent',
            'Creatif Desain',
        ])->get();

        if ($divisions->isEmpty()) return;

        // Ambil daftar kolom aktual agar payload aman
        $paCols  = Schema::getColumnListing('peer_assessments');
        $paiCols = Schema::getColumnListing('peer_assessment_items');

        // Flag kolom header peer_assessments
        $has_status_pa     = in_array('status', $paCols, true);
        $has_division_pa   = in_array('division_id', $paCols, true);
        $has_assessor_pa   = in_array('assessor_id', $paCols, true);
        $has_created_by_pa = in_array('created_by', $paCols, true);
        $has_updated_by_pa = in_array('updated_by', $paCols, true);

        // Flag kolom item peer_assessment_items
        $has_assessor_item = in_array('assessor_id', $paiCols, true);
        $has_aspek_id      = in_array('aspek_id', $paiCols, true);     // <<— ini yang bikin error jika tidak diisi
        $has_aspect_text   = in_array('aspect', $paiCols, true) || in_array('aspect_code', $paiCols, true) || in_array('question_no', $paiCols, true);
        $aspectTextField   = in_array('aspect', $paiCols, true)
                                ? 'aspect'
                                : (in_array('aspect_code', $paiCols, true)
                                    ? 'aspect_code'
                                    : (in_array('question_no', $paiCols, true) ? 'question_no' : null));
        $has_created_by_it = in_array('created_by', $paiCols, true);
        $has_updated_by_it = in_array('updated_by', $paiCols, true);

        // Helper: filter kolom agar hanya kolom yang ada yang dikirim
        $onlyCols = function(array $payload, array $allowedCols): array {
            return array_intersect_key($payload, array_flip($allowedCols));
        };

        // Periode Jan 2024 – Aug 2025
        $start = new \DateTimeImmutable('2024-01-01');
        $end   = new \DateTimeImmutable('2025-08-01');

        // Creator default jika diperlukan
        $defaultCreator = User::whereIn('role', ['hr','owner'])->value('id') ?? User::query()->value('id');

        foreach ($divisions as $div) {
            $employees = User::where('division_id', $div->id)->where('role', 'karyawan')->get();
            if ($employees->count() < 2) {
                // Tidak ada peer untuk divisi dengan 1 karyawan
                continue;
            }

            for ($dt = $start; $dt <= $end; $dt = $dt->modify('+1 month')) {
                $bulan = (int)$dt->format('n');
                $tahun = (int)$dt->format('Y');

                foreach ($employees as $assessee) {
                    $assessors = $employees->where('id', '!=', $assessee->id)->values();

                    foreach ($assessors as $assessor) {
                        // Header assessment (unik per assessee, assessor, bulan, tahun — jika kolomnya ada)
                        $headerKey = [
                            'assessee_id' => $assessee->id,
                            'bulan'       => $bulan,
                            'tahun'       => $tahun,
                        ];
                        if ($has_assessor_pa) {
                            $headerKey['assessor_id'] = $assessor->id;
                        }

                        $headerData = [
                            'division_id' => $has_division_pa ? $div->id : null,
                            'status'      => $has_status_pa ? 'locked' : null,
                            'created_by'  => $has_created_by_pa ? $assessor->id ?? $defaultCreator : null,
                            'updated_by'  => $has_updated_by_pa ? $assessor->id ?? $defaultCreator : null,
                        ];

                        $assessment = PeerAssessment::updateOrCreate(
                            $onlyCols($headerKey, $paCols),
                            $onlyCols($headerData, $paCols)
                        );

                        // Reset items agar idempotent
                        PeerAssessmentItem::where('assessment_id', $assessment->id)->delete();

                        // Buat 5 butir penilaian dengan variasi ringan
                        $seed = ($bulan * 31) + ($assessee->id % 17) + ($assessor->id % 13);
                        $base = 7 + ($seed % 3); // 7,8,9
                        $scores = [
                            $base,
                            min(10, $base + 1),
                            max(1,  $base - 1 + ($seed % 2)),
                            min(10, $base + (($seed % 3) === 0 ? 1 : 0)),
                            $base,
                        ];

                        foreach ($scores as $i => $val) {
                            $payload = [
                                'assessment_id' => $assessment->id,
                                'score'         => (int)$val,
                            ];

                            // Isi aspek_id jika ada (1..5). Ini yang mencegah error NOT NULL.
                            if ($has_aspek_id) {
                                $payload['aspek_id'] = $i + 1;
                            }

                            // Jika ada kolom aspek berbasis teks/kode, isi juga (opsional)
                            if ($has_aspect_text && $aspectTextField) {
                                $payload[$aspectTextField] = 'A' . ($i + 1);
                            }

                            // Assessor pada item jika kolomnya ada
                            if ($has_assessor_item) {
                                $payload['assessor_id'] = $assessor->id;
                            }

                            if ($has_created_by_it) $payload['created_by'] = $assessor->id ?? $defaultCreator;
                            if ($has_updated_by_it) $payload['updated_by'] = $assessor->id ?? $defaultCreator;

                            PeerAssessmentItem::create($onlyCols($payload, $paiCols));
                        }
                    }
                }
            }
        }
    }
}
