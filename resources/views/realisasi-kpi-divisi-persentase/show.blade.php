@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Detail Realisasi — Persentase</h5>
                    <small class="text-muted">
                        {{ $real->kpi?->nama }} • {{ $bulanList[$real->bulan] }} {{ $real->tahun }} •
                        {{ $real->division?->name }}
                    </small>
                </div>
                <a href="{{ route('realisasi-kpi-divisi-persentase.index', ['bulan' => $real->bulan, 'tahun' => $real->tahun, 'division_id' => $real->division_id]) }}"
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

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Target</div>
                                <div class="fs-5 fw-semibold">
                                    {{ rtrim(rtrim(number_format($real->target, 2, '.', ''), '0'), '.') }}
                                    {{ $real->kpi?->satuan }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Realisasi</div>
                                <div class="fs-5 fw-semibold">
                                    {{ !is_null($real->realization) ? rtrim(rtrim(number_format($real->realization, 2, '.', ''), '0'), '.') : '-' }}
                                    {{ $real->kpi?->satuan }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Skor (Berbobot)</div>
                                @php
                                    $weighted =
                                        $real->weighted_score ??
                                        (!is_null($real->score)
                                            ? round($real->score * (float) ($real->kpi?->bobot ?? 1), 2)
                                            : null);
                                @endphp
                                <div class="fs-5 fw-semibold">
                                    @if ($real->status === 'approved' && !is_null($weighted))
                                        {{ rtrim(rtrim(number_format($weighted, 2, '.', ''), '0'), '.') }}
                                    @else
                                        -
                                        <small class="text-muted">Ditampilkan setelah ACC HR</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
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
                    </div>

                    @if (auth()->user()->role === 'hr' && $real->status === 'submitted')
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('realisasi-kpi-divisi-persentase.approve', $real->id) }}"
                                class="js-approve">
                                @csrf
                                <button type="submit" class="btn btn-success">
                                    <i class="bx bx-check me-1"></i> ACC
                                </button>
                            </form>
                            <button class="btn btn-danger" onclick="rejectReal()">Tolak</button>
                            <form id="rejectForm" method="POST"
                                action="{{ route('realisasi-kpi-divisi-persentase.reject', $real->id) }}" class="d-none">
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
        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });

        // Konfirmasi ACC
        document.querySelector('form.js-approve')?.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'ACC realisasi ini?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, ACC',
                cancelButtonText: 'Batal'
            }).then(res => {
                if (res.isConfirmed) e.target.submit();
            });
        });

        // Toast dari session
        @if (session('success'))
            Toast.fire({
                icon: 'success',
                title: @json(session('success'))
            });
        @endif
        @if (session('error'))
            Toast.fire({
                icon: 'error',
                title: @json(session('error'))
            });
        @endif

        // Penolakan dengan alasan wajib
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
