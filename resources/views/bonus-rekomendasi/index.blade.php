@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">Rekomendasi Bonus Karyawan</h5>

                {{-- Toolbar: kiri = per page, kanan = filter --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

                    {{-- Show X entries (kiri) --}}
                    <form method="GET" action="{{ route('bonus.rekomendasi.index') }}"
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
                    <form method="GET" action="{{ route('bonus.rekomendasi.index') }}"
                        class="d-flex align-items-center flex-wrap gap-2">
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
                                placeholder="Cari karyawan...">
                        </div>

                        <button class="btn btn-secondary btn-sm" type="submit">
                            <i class="bx bx-filter-alt me-1"></i> Filter
                        </button>

                        <a href="{{ route('bonus.rekomendasi.index', ['per_page' => $perPage]) }}"
                            class="btn btn-light btn-sm">
                            <i class="bx bx-reset me-1"></i> Reset
                        </a>
                    </form>
                </div>
            </div>

            <div class="card-body">

                {{-- Tabel hasil --}}
                <div class="table-responsive" style="white-space: nowrap; overflow:auto; max-height:65vh;">
                    <table class="table-hover table align-middle">
                        <thead>
                            <tr>
                                <th style="width:60px">#</th>
                                <th>NAMA</th>
                                <th>DIVISI</th>
                                <th>BULAN</th>
                                <th>TAHUN</th>
                                <th class="text-end">KPI UMUM</th>
                                <th class="text-end">KPI DIVISI</th>
                                <th class="text-end">PEER</th>
                                <th class="text-end">SKOR AKHIR</th>
                                <th>LABEL</th>
                                <th class="text-end">BONUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $i => $u)
                                @php
                                    $r = $rows[$u->id] ?? null;

                                    $fmt = function ($v, $dec = 2, $suffix = '') {
                                        if ($v === null) {
                                            return '-';
                                        }
                                        $x = rtrim(rtrim(number_format((float) $v, $dec, '.', ''), '0'), '.');
                                        return $suffix ? $x . $suffix : $x;
                                    };
                                @endphp
                                <tr>
                                    <td>{{ ($users->currentPage() - 1) * $users->perPage() + $i + 1 }}</td>
                                    <td class="fw-semibold">{{ $u->full_name }}</td>
                                    <td>{{ $u->division?->name ?? '-' }}</td>
                                    <td>{{ $bulan ? $bulanList[$bulan] : '-' }}</td>
                                    <td>{{ $tahun ?? '-' }}</td>

                                    {{-- KPI Umum/Divisi/Peer (tanpa bobot AHP) --}}
                                    <td class="text-end">{{ $fmt($r['kpi_umum'] ?? null) }}</td>
                                    <td class="text-end">{{ $fmt($r['kpi_divisi'] ?? null) }}</td>
                                    <td class="text-end">{{ $fmt($r['peer'] ?? null) }}</td>

                                    {{-- Skor Final + Label + Bonus --}}
                                    <td class="fw-semibold text-end">{{ $fmt($r['final'] ?? 0) }}</td>
                                    <td>
                                        @php
                                            $label = $r['label'] ?? '-';
                                            $badge =
                                                $label === 'Sangat Baik'
                                                    ? 'success'
                                                    : ($label === 'Baik'
                                                        ? 'primary'
                                                        : 'secondary');
                                        @endphp
                                        <span class="badge bg-label-{{ $badge }}">{{ $label }}</span>
                                    </td>
                                    <td class="fw-semibold text-end">
                                        {{ $fmt($r['bonus_pct'] ?? 0, 2, '%') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-muted py-4 text-center">Tidak ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Info + Pagination --}}
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
