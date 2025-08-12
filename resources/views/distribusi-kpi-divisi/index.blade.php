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
                        <input type="hidden" name="search" value="{{ $search ?? '' }}">

                        <span class="small text-muted">Show</span>
                        <div class="input-group input-group-sm" style="width:100px;">
                            <select name="per_page" class="form-select" onchange="this.form.submit()">
                                @foreach ([10, 25, 50, 75, 100] as $pp)
                                    <option value="{{ $pp }}" {{ (int) $perPage === $pp ? 'selected' : '' }}>
                                        {{ $pp }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <span class="small text-muted">entries</span>
                    </form>

                    {{-- Filter --}}
                    <form method="GET" action="{{ route('distribusi-kpi-divisi.index') }}"
                        class="d-flex align-items-center ms-auto flex-wrap gap-2">
                        {{-- Persist per_page saat filter --}}
                        <input type="hidden" name="per_page" value="{{ $perPage }}">

                        <div class="input-group input-group-sm" style="width: 200px;">
                            <span class="input-group-text"><i class="bx bx-calendar"></i>&nbsp;Bulan</span>
                            <select name="bulan" class="form-select">
                                <option value="" {{ is_null($bulan) ? 'selected' : '' }}>Pilih Bulan</option>
                                @foreach ($bulanList as $num => $label)
                                    <option value="{{ $num }}"
                                        {{ (string) $bulan === (string) $num ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="input-group input-group-sm" style="width: 180px;">
                            <span class="input-group-text"><i class="bx bx-calendar-event"></i>&nbsp;Tahun</span>
                            <input type="number" name="tahun" class="form-control" placeholder="YYYY"
                                min="{{ date('Y') - 5 }}" max="{{ date('Y') + 5 }}" value="{{ $tahun ?? '' }}">
                        </div>

                        @if (in_array($me->role, ['owner', 'hr']))
                            <div class="input-group input-group-sm" style="width: 240px;">
                                <span class="input-group-text"><i class="bx bx-buildings"></i>&nbsp;Divisi</span>
                                <select name="division_id" class="form-select">
                                    <option value="" {{ empty($division_id) ? 'selected' : '' }}>Pilih Divisi
                                    </option>
                                    @foreach ($divisions as $d)
                                        <option value="{{ $d->id }}"
                                            {{ (string) $division_id === (string) $d->id ? 'selected' : '' }}>
                                            {{ $d->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="input-group input-group-sm" style="width: 260px;">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control"
                                placeholder="Cari KPI...">
                        </div>

                        <button class="btn btn-secondary btn-sm" type="submit">
                            <i class="bx bx-filter-alt me-1"></i> Filter
                        </button>

                        <a href="{{ route('distribusi-kpi-divisi.index', ['per_page' => $perPage]) }}"
                            class="btn btn-light btn-sm">
                            <i class="bx bx-reset me-1"></i> Reset
                        </a>
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
                                <th class="text-center">STATUS</th>
                                <th style="width:320px">AKSI</th>
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
                                        <td class="text-end">
                                            {{ rtrim(rtrim(number_format($k->target, 2, '.', ''), '0'), '.') }}
                                            {{ $k->satuan }}
                                        </td>
                                        <td>{{ $bulanList[$k->bulan] ?? $k->bulan }}</td>
                                        <td>{{ $k->tahun }}</td>
                                        {{-- STATUS icon-only + tooltip --}}
                                        <td class="text-center">
                                            @php
                                                $statusIcon = 'bx-file';
                                                $statusClass = 'text-secondary';
                                                $statusTitle = 'Belum diinput';
                                                if ($dist && $hasItems) {
                                                    if ($dist->status === 'approved') {
                                                        $statusIcon = 'bx-check-circle';
                                                        $statusClass = 'text-success';
                                                        $statusTitle = 'Approved';
                                                    } elseif ($dist->status === 'submitted') {
                                                        $statusIcon = 'bx-time-five';
                                                        $statusClass = 'text-warning';
                                                        $statusTitle = 'Menunggu persetujuan';
                                                    } elseif ($dist->status === 'rejected') {
                                                        $statusIcon = 'bx-x-circle';
                                                        $statusClass = 'text-danger';
                                                        $statusTitle = 'Rejected';
                                                    } else {
                                                        $statusIcon = 'bx-refresh';
                                                        $statusClass = 'text-secondary';
                                                        $statusTitle = 'Perlu input ulang';
                                                    }
                                                }
                                            @endphp
                                            <i class="bx {{ $statusIcon }} {{ $statusClass }}"
                                                title="{{ $statusTitle }}" aria-label="{{ $statusTitle }}"></i>
                                        </td>
                                        {{-- AKSI icon-only + info di samping (nowrap) --}}
                                        <td>
                                            @if ($me->role === 'leader')
                                                @if (!$dist || $dist->status === 'rejected' || $dist->status === 'stale' || !$hasItems)
                                                    <div class="d-flex align-items-center flex-nowrap gap-2">
                                                        {{-- Edit / Ajukan Ulang / Input Ulang --}}
                                                        <a class="btn btn-icon btn-sm btn-primary"
                                                            href="{{ route('distribusi-kpi-divisi.create', ['kpi_id' => $k->id]) }}"
                                                            title="{{ $dist && $dist->status === 'rejected' ? 'Ajukan Ulang' : (!$hasItems ? 'Input' : 'Input Ulang') }}"
                                                            aria-label="{{ $dist && $dist->status === 'rejected' ? 'Ajukan Ulang' : (!$hasItems ? 'Input' : 'Input Ulang') }}">
                                                            <i class="bx bx-edit"></i>
                                                        </a>

                                                        {{-- Info right of icon (nowrap) --}}
                                                        @if ($dist && $dist->status === 'rejected' && $dist->hr_note)
                                                            <small class="text-danger d-flex align-items-center"
                                                                style="white-space: nowrap;">
                                                                <i class="bx bx-error-circle me-1"></i>Ditolak:
                                                                {{ $dist->hr_note }}
                                                            </small>
                                                        @elseif ($dist && $dist->status === 'stale')
                                                            <small class="text-warning d-flex align-items-center"
                                                                style="white-space: nowrap;">
                                                                <i class="bx bx-error me-1"></i>Perubahan data KPI. Mohon
                                                                input ulang.
                                                            </small>
                                                        @elseif (!$hasItems)
                                                            <small class="text-muted" style="white-space: nowrap;">
                                                                <i class="bx bx-info-circle me-1"></i>Belum ada item
                                                                distribusi.
                                                            </small>
                                                        @endif
                                                    </div>
                                                @elseif($dist->status === 'submitted')
                                                    <span class="badge bg-label-warning" title="Menunggu persetujuan"
                                                        aria-label="Menunggu persetujuan">
                                                        <i class="bx bx-time-five"></i>
                                                    </span>
                                                @else
                                                    <a class="btn btn-icon btn-sm btn-outline-secondary"
                                                        href="{{ route('distribusi-kpi-divisi.show', ['distribution_id' => $dist->id, 'kpi_id' => $k->id]) }}"
                                                        title="Detail" aria-label="Detail">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                @endif
                                            @elseif(in_array($me->role, ['hr', 'owner']))
                                                @if ($dist && $hasItems)
                                                    <a class="btn btn-icon btn-sm btn-outline-secondary"
                                                        href="{{ route('distribusi-kpi-divisi.show', ['distribution_id' => $dist->id, 'kpi_id' => $k->id]) }}"
                                                        title="Detail" aria-label="Detail">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            @else
                                                @if ($dist && $dist->status === 'approved' && $hasItems)
                                                    <a class="btn btn-icon btn-sm btn-outline-secondary"
                                                        href="{{ route('distribusi-kpi-divisi.show', ['distribution_id' => $dist->id, 'kpi_id' => $k->id]) }}"
                                                        title="Detail" aria-label="Detail">
                                                        <i class="bx bx-show"></i>
                                                    </a>
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
