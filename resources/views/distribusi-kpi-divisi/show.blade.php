@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Detail Distribusi</h5>
                    <small class="text-muted">
                        {{ $kpi->nama }} • {{ $kpi->division?->name ?? '-' }} •
                        {{ $bulanList[$distribution->bulan] ?? $distribution->bulan }} {{ $distribution->tahun }}
                    </small>
                </div>
                <a href="{{ route('distribusi-kpi-divisi.index', ['bulan' => $distribution->bulan, 'tahun' => $distribution->tahun, 'division_id' => $distribution->division_id]) }}"
                    class="btn btn-sm btn-outline-secondary">Kembali</a>
            </div>

            <div class="card-body">
                {{-- Status banner selaras dengan Realisasi KPI Umum --}}
                @if ($distribution->status === 'stale')
                    <div class="alert alert-secondary">
                        Distribusi ini <strong>tidak valid</strong> karena ada perubahan KPI Divisi. Leader harus input
                        ulang.
                    </div>
                @endif
                @if ($distribution->status === 'rejected' && $distribution->hr_note)
                    <div class="alert alert-danger">Ditolak HR: {{ $distribution->hr_note }}</div>
                @endif

                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <span class="me-2">Status:</span>
                        @if ($distribution->status === 'approved')
                            <span class="badge bg-label-success">Approved</span>
                        @elseif ($distribution->status === 'submitted')
                            <span class="badge bg-label-warning">Menunggu</span>
                        @elseif ($distribution->status === 'rejected')
                            <span class="badge bg-label-danger">Rejected</span>
                        @else
                            <span class="badge bg-label-secondary">Stale</span>
                        @endif
                    </div>
                </div>

                <div class="table-responsive" style="max-height:65vh; overflow:auto;">
                    <table class="table-hover table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Karyawan</th>
                                <th class="text-end" style="width:220px;">Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $sum = 0; @endphp
                            @foreach ($employees as $emp)
                                @php
                                    $v = (float) ($alloc[$emp->id] ?? 0);
                                    $sum += $v;
                                @endphp
                                <tr>
                                    <td>{{ $emp->full_name }}</td>
                                    <td class="text-end">{{ rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="small text-muted">
                        Total alokasi:
                        <strong>{{ rtrim(rtrim(number_format($sum, 2, '.', ''), '0'), '.') }} {{ $kpi->satuan }}</strong>
                        &nbsp;|&nbsp;
                        Target KPI:
                        <strong>{{ rtrim(rtrim(number_format($kpi->target, 2, '.', ''), '0'), '.') }}
                            {{ $kpi->satuan }}</strong>
                    </div>

                    @if ($me->role === 'hr' && $distribution->status === 'submitted')
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('distribusi-kpi-divisi.approve', $distribution) }}"
                                class="js-approve">
                                @csrf
                                <button type="submit" class="btn btn-success"><i class="bx bx-check me-1"></i> ACC</button>
                            </form>

                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bx bx-x me-1"></i> Tolak
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Reject --}}
    @if ($me->role === 'hr' && $distribution->status === 'submitted')
        <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST"
                    action="{{ route('distribusi-kpi-divisi.reject', $distribution) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tolak Distribusi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Keterangan</label>
                        <textarea name="hr_note" class="form-control" rows="4" required placeholder="Alasan penolakan..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
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

        // Konfirmasi ACC (selaras dgn Realisasi KPI Umum)
        document.querySelector('form.js-approve')?.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'ACC distribusi ini?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, ACC',
                cancelButtonText: 'Batal'
            }).then(res => {
                if (res.isConfirmed) e.target.submit();
            });
        });

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
    </script>
@endpush
