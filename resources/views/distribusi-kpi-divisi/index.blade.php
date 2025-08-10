@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">Distribusi Target — KPI Divisi (Kuantitatif)</h5>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    {{-- Per page --}}
                    <form method="GET" action="{{ route('distribusi-kpi-divisi.index') }}"
                        class="d-flex align-items-center gap-2">
                        <input type="hidden" name="bulan" value="{{ $bulan }}">
                        <input type="hidden" name="tahun" value="{{ $tahun }}">
                        @if (in_array($me->role, ['owner', 'hr']))
                            <input type="hidden" name="division_id" value="{{ $division_id }}">
                        @endif
                        <span class="small text-muted">Show</span>
                        <div class="input-group input-group-sm" style="width:100px;">
                            <select name="per_page" class="form-select" onchange="this.form.submit()">
                                @foreach ([10, 25, 50, 75, 100] as $pp)
                                    <option value="{{ $pp }}" {{ (int) $perPage === $pp ? 'selected' : '' }}>
                                        {{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="small text-muted">entries</span>
                    </form>

                    {{-- Filter kanan --}}
                    <form method="GET" action="{{ route('distribusi-kpi-divisi.index') }}"
                        class="d-flex align-items-center ms-auto flex-wrap gap-2">
                        <div class="input-group input-group-sm" style="width:160px;">
                            <span class="input-group-text">Bulan</span>
                            <select name="bulan" class="form-select">
                                <option value="" {{ is_null($bulan) ? 'selected' : '' }}>Pilih Bulan</option>
                                @foreach ($bulanList as $num => $label)
                                    <option value="{{ $num }}"
                                        {{ (string) $bulan === (string) $num ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="input-group input-group-sm" style="width:140px;">
                            <span class="input-group-text">Tahun</span>
                            <input type="number" name="tahun" class="form-control" value="{{ $tahun }}">
                        </div>
                        @if (in_array($me->role, ['owner', 'hr']))
                            <div class="input-group input-group-sm" style="width:220px;">
                                <span class="input-group-text">Divisi</span>
                                <select name="division_id" class="form-select">
                                    <option value="" {{ empty($division_id) ? 'selected' : '' }}>Pilih Divisi</option>
                                    @foreach ($divisions as $d)
                                        <option value="{{ $d->id }}"
                                            {{ (string) $division_id === (string) $d->id ? 'selected' : '' }}>
                                            {{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="input-group input-group-sm" style="width:220px;">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control"
                                placeholder="Cari KPI...">
                        </div>
                        <button class="btn btn-secondary btn-sm" type="submit"><i class="bx bx-filter-alt me-1"></i>
                            Filter</button>
                    </form>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive" style="white-space:nowrap;overflow:auto;max-height:65vh;">
                    <table class="table-hover table align-middle">
                        <thead>
                            <tr>
                                <th style="width:60px">#</th>
                                <th>KPI (Kuantitatif)</th>
                                <th>DIVISI</th>
                                <th class="text-end">TARGET</th>
                                <th>BULAN</th>
                                <th>TAHUN</th>
                                <th class="text-end">STATUS</th>
                                <th style="width:160px">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (is_null($bulan) || is_null($tahun) || (in_array($me->role, ['owner', 'hr']) && empty($division_id)))
                                <tr>
                                    <td colspan="8" class="text-muted py-4 text-center">Silakan pilih filter.</td>
                                </tr>
                            @else
                                @forelse($kpis as $i=>$k)
                                    @php
                                        $dist = $distribution ?? null;
                                        $hasItems = $dist ? ($itemsCountByKpi[$k->id] ?? 0) > 0 : false;
                                    @endphp
                                    <tr>
                                        <td>{{ ($kpis->currentPage() - 1) * $kpis->perPage() + $i + 1 }}</td>
                                        <td class="fw-semibold">{{ $k->nama }}</td>
                                        <td>{{ $k->division?->name ?? '-' }}</td>
                                        <td class="text-end">{{ rtrim(rtrim(number_format($k->target, 2, '.', ''), '0'), '.') }}
                                            {{ $k->satuan }}</td>
                                        <td>{{ $bulanList[$k->bulan] }}</td>
                                        <td>{{ $k->tahun }}</td>
                                        <td class="text-end">
                                            @if (!$dist || !$hasItems)
                                                <span class="badge bg-label-secondary">Belum diinput</span>
                                            @else
                                                @if ($dist->status === 'approved')
                                                    <span class="badge bg-label-success">Approved</span>
                                                @elseif($dist->status === 'submitted')
                                                    <span class="badge bg-label-warning">Tunggu</span>
                                                @elseif($dist->status === 'rejected')
                                                    <span class="badge bg-label-danger">Rejected</span>
                                                @else
                                                    <span class="badge bg-label-secondary">Perlu input ulang</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @if ($me->role === 'leader')
                                                @if (!$dist || $dist->status === 'rejected' || $dist->status === 'stale' || !$hasItems)
                                                    <a class="btn btn-sm btn-primary"
                                                        href="{{ route('distribusi-kpi-divisi.create', ['kpi_id' => $k->id]) }}">
                                                        {{ $dist && $dist->status === 'rejected' ? 'Ajukan Ulang' : (!$hasItems ? 'Input' : 'Input Ulang') }}
                                                    </a>
                                                    @if ($dist && $dist->status === 'rejected' && $dist->hr_note)
                                                        <small class="text-danger d-block mt-1">Ditolak:
                                                            {{ $dist->hr_note }}</small>
                                                    @endif
                                                @elseif($dist->status === 'submitted')
                                                    <span class="badge bg-label-warning">Tunggu</span>
                                                @else
                                                    <a class="btn btn-sm btn-outline-secondary"
                                                        href="{{ route('distribusi-kpi-divisi.show', ['distribution_id' => $dist->id, 'kpi_id' => $k->id]) }}">
                                                        Detail</a>
                                                @endif
                                            @elseif(in_array($me->role, ['hr', 'owner']))
                                                @if ($dist && $hasItems)
                                                    <a class="btn btn-sm btn-outline-secondary"
                                                        href="{{ route('distribusi-kpi-divisi.show', ['distribution_id' => $dist->id, 'kpi_id' => $k->id]) }}">
                                                        Detail</a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            @else
                                                @if ($dist && $dist->status === 'approved' && $hasItems)
                                                    <a class="btn btn-sm btn-outline-secondary"
                                                        href="{{ route('distribusi-kpi-divisi.show', ['distribution_id' => $dist->id, 'kpi_id' => $k->id]) }}">
                                                        Detail</a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-muted py-4 text-center">Tidak ada KPI kuantitatif.
                                        </td>
                                    </tr>
                                @endforelse
                            @endif
                        </tbody>
                    </table>
                </div>

                @php
                    $from = $kpis->count() ? $kpis->firstItem() : 0;
                    $to = $kpis->count() ? $kpis->lastItem() : 0;
                    $total = $kpis->total();
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $total }}
                        entries</small>
                    @if ($kpis->hasPages())
                        {{ $kpis->onEachSide(1)->links() }}
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
