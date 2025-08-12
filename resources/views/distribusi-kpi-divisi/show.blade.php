@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Detail Distribusi — {{ $kpi->nama }} ({{ $bulanList[$distribution->bulan] }}
                    {{ $distribution->tahun }})</h5>
                <a href="{{ route('distribusi-kpi-divisi.index', ['bulan' => $distribution->bulan, 'tahun' => $distribution->tahun, 'division_id' => $distribution->division_id]) }}"
                    class="btn btn-sm btn-outline-secondary">Kembali</a>
            </div>

            <div class="card-body">
                @if ($distribution->status === 'stale')
                    <div class="alert alert-secondary mb-3">
                        Distribusi <strong>tidak valid</strong> karena ada perubahan KPI Divisi. Leader harus input ulang.
                    </div>
                @endif
                @if ($distribution->status === 'rejected' && $distribution->hr_note)
                    <div class="alert alert-danger mb-3">Ditolak HR: {{ $distribution->hr_note }}</div>
                @endif

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
                        Total alokasi: <strong>{{ rtrim(rtrim(number_format($sum, 2, '.', ''), '0'), '.') }}
                            {{ $kpi->satuan }}</strong>
                        &nbsp;|&nbsp; Target KPI:
                        <strong>{{ rtrim(rtrim(number_format($kpi->target, 2, '.', ''), '0'), '.') }}
                            {{ $kpi->satuan }}</strong>
                    </div>

                    @if ($me->role === 'hr' && $distribution->status === 'submitted')
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('distribusi-kpi-divisi.approve', $distribution) }}">
                                @csrf
                                <button type="submit" class="btn btn-success"><i class="bx bx-check me-1"></i> ACC</button>
                            </form>
                            <button class="btn btn-danger" onclick="rejectDist()">Tolak</button>
                            <form id="rejectForm" method="POST"
                                action="{{ route('distribusi-kpi-divisi.reject', $distribution) }}" class="d-none">
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
        function rejectDist() {
            Swal.fire({
                    title: 'Alasan Penolakan',
                    input: 'textarea',
                    inputPlaceholder: 'Tuliskan alasan...',
                    showCancelButton: true,
                    confirmButtonText: 'Tolak',
                    cancelButtonText: 'Batal'
                })
                .then(res => {
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
