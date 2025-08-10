@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">Realisasi KPI Divisi — Kualitatif</h5>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <form method="GET" action="{{ route('realisasi-kpi-divisi-kualitatif.index') }}"
                        class="d-flex align-items-center gap-2">
                        <input type="hidden" name="bulan" value="{{ $bulan }}">
                        <input type="hidden" name="tahun" value="{{ $tahun }}">
                        @if (in_array($me->role, ['owner', 'hr']))
                            <input type="hidden" name="division_id" value="{{ $division_id }}">
                        @endif
                        <span class="small text-muted">Show</span>
                        <div class="input-group input-group-sm" style="width: 100px;">
                            <select name="per_page" class="form-select" onchange="this.form.submit()">
                                @foreach ([10, 25, 50, 75, 100] as $pp)
                                    <option value="{{ $pp }}" {{ (int) $perPage === $pp ? 'selected' : '' }}>
                                        {{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="small text-muted">entries</span>
                    </form>

                    <form method="GET" action="{{ route('realisasi-kpi-divisi-kualitatif.index') }}"
                        class="d-flex align-items-center ms-auto flex-wrap gap-2">
                        <div class="input-group input-group-sm" style="width: 170px;">
                            <span class="input-group-text">Bulan</span>
                            <select name="bulan" class="form-select">
                                <option value="" {{ is_null($bulan) ? 'selected' : '' }}>Pilih Bulan</option>
                                @foreach ($bulanList as $num => $label)
                                    <option value="{{ $num }}"
                                        {{ (string) $bulan === (string) $num ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="input-group input-group-sm" style="width: 160px;">
                            <span class="input-group-text">Tahun</span>
                            <select name="tahun" class="form-select">
                                <option value="" {{ is_null($tahun) ? 'selected' : '' }}>Pilih Tahun</option>
                                @for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++)
                                    <option value="{{ $y }}"
                                        {{ (string) $tahun === (string) $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        @if (in_array($me->role, ['owner', 'hr']))
                            <div class="input-group input-group-sm" style="width: 220px;">
                                <span class="input-group-text">Divisi</span>
                                <select name="division_id" class="form-select">
                                    <option value="" {{ empty($division_id) ? 'selected' : '' }}>Pilih Divisi
                                    </option>
                                    @foreach ($divisions as $d)
                                        <option value="{{ $d->id }}"
                                            {{ (string) $division_id === (string) $d->id ? 'selected' : '' }}>
                                            {{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="input-group input-group-sm" style="width: 200px;">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control"
                                placeholder="Cari karyawan...">
                        </div>

                        <button class="btn btn-secondary btn-sm" type="submit">
                            <i class="bx bx-filter-alt me-1"></i> Filter
                        </button>
                    </form>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive" style="white-space: nowrap; overflow:auto; max-height:65vh;">
                    <table class="table-hover table align-middle">
                        <thead>
                            <tr>
                                <th style="width:60px">#</th>
                                <th>NAMA</th>
                                <th>DIVISI</th>
                                <th>BULAN</th>
                                <th>TAHUN</th>
                                <th class="text-end">SKOR KPI DIVISI</th>
                                <th style="width:160px">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (is_null($bulan) || is_null($tahun) || (in_array($me->role, ['owner', 'hr']) && empty($division_id)))
                                <tr>
                                    <td colspan="7" class="text-muted py-4 text-center">Silakan pilih filter.</td>
                                </tr>
                            @else
                                @forelse($users as $i => $u)
                                    @php $r = $realByUser[$u->id] ?? null; @endphp
                                    <tr>
                                        <td>{{ ($users->currentPage() - 1) * $users->perPage() + $i + 1 }}</td>
                                        <td class="fw-semibold">{{ $u->full_name }}</td>
                                        <td>{{ $u->division?->name ?? '-' }}</td>
                                        <td>{{ $bulan ? $bulanList[$bulan] : '-' }}</td>
                                        <td>{{ $tahun ?? '-' }}</td>
                                        <td class="text-end">
                                            @if ($r && $r->status === 'approved')
                                                {{ rtrim(rtrim(number_format(round($r->total_score, 2), 2, '.', ''), '0'), '.') }}%
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if ($me->role === 'leader')
                                                @if (!$r || $r->status === 'rejected' || $r->status === 'stale')
                                                    <a class="btn btn-sm btn-primary"
                                                        href="{{ route('realisasi-kpi-divisi-kualitatif.create', ['user_id' => $u->id, 'bulan' => $bulan, 'tahun' => $tahun]) }}">
                                                        {{ $r && $r->status === 'rejected' ? 'Ajukan Ulang' : ($r && $r->status === 'stale' ? 'Input Ulang' : 'Input') }}
                                                    </a>
                                                    @if ($r && $r->status === 'stale')
                                                        <small
                                                            class="text-warning d-block mt-1">{{ $r->hr_note ?? 'Perlu input ulang: data berubah.' }}</small>
                                                    @endif
                                                    @if ($r && $r->status === 'rejected' && $r->hr_note)
                                                        <small class="text-danger d-block mt-1">Ditolak:
                                                            {{ $r->hr_note }}</small>
                                                    @endif
                                                @elseif ($r->status === 'submitted')
                                                    <span class="badge bg-label-warning">Tunggu</span>
                                                @else
                                                    <a class="btn btn-sm btn-outline-secondary"
                                                        href="{{ route('realisasi-kpi-divisi-kualitatif.show', $r->id) }}">Detail</a>
                                                @endif
                                            @elseif ($me->role === 'hr' || $me->role === 'owner')
                                                @if ($r)
                                                    <a class="btn btn-sm btn-outline-secondary"
                                                        href="{{ route('realisasi-kpi-divisi-kualitatif.show', $r->id) }}">Detail</a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            @else
                                                {{-- karyawan --}}
                                                @if ($me->id === $u->id && $r)
                                                    <a class="btn btn-sm btn-outline-secondary"
                                                        href="{{ route('realisasi-kpi-divisi-kualitatif.show', $r->id) }}">Detail</a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-muted py-4 text-center">Tidak ada data.</td>
                                    </tr>
                                @endforelse
                            @endif
                        </tbody>
                    </table>
                </div>

                @php
                    $from = $users->count() ? $users->firstItem() : 0;
                    $to = $users->count() ? $users->lastItem() : 0;
                    $total = $users->total();
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $total }}
                        entries</small>
                    @if ($users->hasPages())
                        {{ $users->onEachSide(1)->links() }}
                    @else
                        <nav>
                            <ul class="pagination mb-0">
                                <li class="page-item active"><span class="page-link">1</span></li>
                            </ul>
                        </nav>
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
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
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
        @if ($errors->any())
            @foreach ($errors->take(3) as $e)
                Toast.fire({
                    icon: 'error',
                    title: @json($e)
                });
            @endforeach
        @endif
    </script>
@endpush
