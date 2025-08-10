@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Detail Realisasi (Kualitatif) — {{ $real->user->full_name }}</h5>
                    <small class="text-muted">{{ $bulanList[$real->bulan] }} {{ $real->tahun }} •
                        {{ $real->division?->name }}</small>
                </div>
                <a href="{{ route('realisasi-kpi-divisi-kualitatif.index', ['bulan' => $real->bulan, 'tahun' => $real->tahun, 'division_id' => $real->division_id]) }}"
                    class="btn btn-sm btn-outline-secondary">Kembali</a>
            </div>

            <div class="card-body">
                @if ($real->status === 'stale')
                    <div class="alert alert-secondary">Realisasi <strong>tidak valid</strong> karena ada perubahan data.
                        Leader perlu input ulang.</div>
                @endif
                @if ($real->status === 'rejected' && $real->hr_note)
                    <div class="alert alert-danger">Ditolak HR: {{ $real->hr_note }}</div>
                @endif

                <div class="table-responsive" style="max-height:65vh; overflow:auto; white-space:nowrap;">
                    <table class="table-hover table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nama KPI</th>
                                <th class="text-end" style="width:160px;">Target</th>
                                <th class="text-end" style="width:160px;">Realisasi</th>
                                <th class="text-end" style="width:160px;">Skor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $sum = 0; @endphp
                            @foreach ($items as $it)
                                @php
                                    $k = $kpis[$it->kpi_divisi_id] ?? null;
                                    $sc =
                                        $it->score !== null
                                            ? rtrim(rtrim(number_format($it->score, 2, '.', ''), '0'), '.')
                                            : '-';
                                    $sum += (float) ($it->score ?? 0) * (float) ($k?->bobot ?? 0);
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $k?->nama ?? '-' }}</td>
                                    <td class="text-end">
                                        {{ rtrim(rtrim(number_format($it->target, 2, '.', ''), '0'), '.') }}
                                        {{ $k?->satuan }}</td>
                                    <td class="text-end">
                                        {{ rtrim(rtrim(number_format($it->realization, 2, '.', ''), '0'), '.') }}
                                        {{ $k?->satuan }}</td>
                                    <td class="text-end">{{ $sc }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <span class="me-3">Status:
                            @if ($real->status === 'approved')
                                <span class="badge bg-label-success">Approved</span>
                            @elseif($real->status === 'submitted')
                                <span class="badge bg-label-warning">Submitted</span>
                            @elseif($real->status === 'stale')
                                <span class="badge bg-label-secondary">Stale</span>
                            @else
                                <span class="badge bg-label-danger">Rejected</span>
                            @endif
                        </span>
                        @if (!is_null($total) && $real->status !== 'stale')
                            <span>Total Skor (Σ w·s):
                                <strong>{{ rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') }}%</strong></span>
                        @elseif($real->status === 'approved' && !is_null($real->total_score))
                            <span>Total Skor:
                                <strong>{{ rtrim(rtrim(number_format($real->total_score, 2, '.', ''), '0'), '.') }}%</strong></span>
                        @else
                            <span class="text-muted">Total skor menunggu bobot AHP atau verifikasi.</span>
                        @endif
                    </div>

                    @if (auth()->user()->role === 'hr' && $real->status === 'submitted')
                        <div class="d-flex gap-2">
                            <form method="POST"
                                action="{{ route('realisasi-kpi-divisi-kualitatif.approve', $real->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-success"><i class="bx bx-check me-1"></i> ACC</button>
                            </form>

                            <button class="btn btn-danger" onclick="rejectReal()">Tolak</button>
                            <form id="rejectForm" method="POST"
                                action="{{ route('realisasi-kpi-divisi-kualitatif.reject', $real->id) }}" class="d-none">
                                @csrf
                                <input type="hidden" name="hr_note" id="hr_note">
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function rejectReal() {
            Swal.fire({
                title: 'Alasan Penolakan',
                input: 'textarea',
                inputPlaceholder: 'Tuliskan alasan...',
                showCancelButton: true,
                confirmButtonText: 'Tolak',
                cancelButtonText: 'Batal'
            }).then(res => {
                if (res.isConfirmed && res.value) {
                    document.getElementById('hr_note').value = res.value;
                    document.getElementById('rejectForm').submit();
                } else if (res.isConfirmed) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Alasan wajib diisi',
                        timer: 1800,
                        showConfirmButton: false
                    });
                }
            });
        }
    </script>
@endpush
