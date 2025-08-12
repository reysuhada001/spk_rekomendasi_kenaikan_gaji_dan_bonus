@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">Realisasi KPI Umum</h5>

                {{-- Toolbar: kiri per page, kanan filter --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

                    {{-- Per Page (kiri) --}}
                    <form method="GET" action="{{ route('realisasi-kpi-umum.index') }}"
                        class="d-flex align-items-center gap-2">
                        <input type="hidden" name="bulan" value="{{ $bulan }}">
                        <input type="hidden" name="tahun" value="{{ $tahun }}">
                        @if (in_array($me->role, ['owner', 'hr']))
                            <input type="hidden" name="division_id" value="{{ $division_id }}">
                        @endif
                        <input type="hidden" name="search" value="{{ $search ?? '' }}">

                        <label class="small text-muted mb-0">Show</label>
                        <div class="input-group input-group-sm" style="width: 100px;">
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

                    {{-- Filter (kanan) --}}
                    <form method="GET" action="{{ route('realisasi-kpi-umum.index') }}"
                        class="d-flex align-items-center flex-wrap gap-2">
                        {{-- persist per_page --}}
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
                                placeholder="Cari karyawan...">
                        </div>

                        <button class="btn btn-secondary btn-sm" type="submit">
                            <i class="bx bx-filter-alt me-1"></i> Filter
                        </button>

                        <a href="{{ route('realisasi-kpi-umum.index', ['per_page' => $perPage]) }}"
                            class="btn btn-light btn-sm">
                            <i class="bx bx-reset me-1"></i> Reset
                        </a>
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
                                <th class="text-end">SKOR KPI UMUM</th>
                                <th style="width:340px">AKSI</th>
                            </tr>
                        </thead>

                        <tbody>
                            {{-- Belum pilih periode → kosongkan tabel --}}
                            @if (is_null($bulan) || is_null($tahun))
                                <tr>
                                    <td colspan="7" class="text-muted py-4 text-center">Silakan pilih Bulan & Tahun
                                        terlebih dahulu.</td>
                                </tr>
                            @else
                                @forelse ($users as $i => $u)
                                    @php
                                        $r = $realByUser[$u->id] ?? null;
                                        $needsReinput = !$r || in_array($r->status, ['rejected', 'stale'], true);
                                    @endphp
                                    <tr>
                                        <td>{{ ($users->currentPage() - 1) * $users->perPage() + $i + 1 }}</td>
                                        <td class="fw-semibold">{{ $u->full_name }}</td>
                                        <td>{{ $u->division?->name ?? '-' }}</td>
                                        <td>{{ $bulan ? $bulanList[$bulan] : '-' }}</td>
                                        <td>{{ $tahun ?? '-' }}</td>
                                        <td class="text-end">
                                            @if ($r && $r->status === 'approved')
                                                {{ rtrim(rtrim(number_format(round($r->total_score, 2), 2, '.', ''), '0'), '.') }}%
                                            @elseif ($r && $r->status === 'stale')
                                                <span class="text-muted"
                                                    title="Perlu input ulang karena perubahan KPI">-</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            {{-- Aksi pakai ikon + info di SAMPING tombol (no wrap) --}}
                                            @if ($me->role === 'leader')
                                                @if ($needsReinput)
                                                    <div class="d-flex align-items-center flex-nowrap gap-2">
                                                        {{-- Input / Ajukan Ulang / Input Ulang --}}
                                                        <a class="btn btn-sm btn-primary"
                                                            href="{{ route('realisasi-kpi-umum.create', ['user' => $u->id, 'bulan' => $bulan, 'tahun' => $tahun]) }}"
                                                            title="@if (!$r) Input @elseif($r->status === 'rejected') Ajukan Ulang @else Input Ulang @endif"
                                                            aria-label="@if (!$r) Input @elseif($r->status === 'rejected') Ajukan Ulang @else Input Ulang @endif">
                                                            <i class="bx bx-edit"></i>
                                                        </a>

                                                        {{-- Pesan ringkas di samping tombol --}}
                                                        @if ($r && $r->status === 'rejected' && $r->hr_note)
                                                            <small class="text-danger d-flex align-items-center"
                                                                style="white-space: nowrap;">
                                                                <i class="bx bx-error-circle me-1"></i>{{ $r->hr_note }}
                                                            </small>
                                                        @elseif ($r && $r->status === 'stale')
                                                            <small class="text-warning d-flex align-items-center"
                                                                style="white-space: nowrap;">
                                                                <i class="bx bx-error me-1"></i>Perubahan data KPI. Mohon
                                                                input ulang.
                                                            </small>
                                                        @endif
                                                    </div>
                                                @elseif ($r && $r->status === 'submitted')
                                                    <span class="badge bg-label-warning" title="Menunggu persetujuan"
                                                        aria-label="Menunggu persetujuan">
                                                        <i class="bx bx-time-five"></i>
                                                    </span>
                                                @else
                                                    <a class="btn btn-sm btn-outline-secondary"
                                                        href="{{ route('realisasi-kpi-umum.show', $r->id) }}"
                                                        title="Detail" aria-label="Detail">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                @endif
                                            @elseif (in_array($me->role, ['hr', 'owner']))
                                                @if ($r)
                                                    @if ($r->status === 'stale')
                                                        <span class="badge bg-label-secondary" title="Perlu input ulang"
                                                            aria-label="Perlu input ulang">
                                                            <i class="bx bx-revision"></i>
                                                        </span>
                                                    @else
                                                        <a class="btn btn-sm btn-outline-secondary"
                                                            href="{{ route('realisasi-kpi-umum.show', $r->id) }}"
                                                            title="Detail" aria-label="Detail">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            @else
                                                {{-- karyawan --}}
                                                @if ($me->id === $u->id && $r && $r->status !== 'stale')
                                                    <a class="btn btn-sm btn-outline-secondary"
                                                        href="{{ route('realisasi-kpi-umum.show', $r->id) }}"
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
                                        <td colspan="7" class="text-muted py-4 text-center">Tidak ada data.</td>
                                    </tr>
                                @endforelse
                            @endif
                        </tbody>
                    </table>
                </div>

                {{-- Info + Pagination --}}
                @php
                    $hasPeriod = !is_null($bulan) && !is_null($tahun);
                    $from = $hasPeriod && $users->count() ? $users->firstItem() : 0;
                    $to = $hasPeriod && $users->count() ? $users->lastItem() : 0;
                    $total = $hasPeriod ? $users->total() : 0;
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $total }}
                        entries</small>
                    @if ($hasPeriod && $users->hasPages())
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
            position: 'bottom-end',
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
            @foreach ($errors->take(3) as $err)
                Toast.fire({
                    icon: 'error',
                    title: @json($err)
                });
            @endforeach
        @endif
    </script>
@endpush
