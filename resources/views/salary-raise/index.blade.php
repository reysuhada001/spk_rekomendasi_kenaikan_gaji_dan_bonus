@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">Rekomendasi Kenaikan Gaji (Tahunan)</h5>

                {{-- Toolbar --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

                    {{-- Per Page (kiri) --}}
                    <form method="GET" action="{{ route('salary.raise.index') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="tahun" value="{{ $tahun }}">
                        @if (in_array($me->role, ['owner', 'hr']))
                            <input type="hidden" name="division_id" value="{{ $division_id }}">
                        @endif
                        <input type="hidden" name="search" value="{{ $search }}">

                        <label class="small text-muted mb-0">Show</label>
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

                    {{-- Filter (kanan) --}}
                    <form method="GET" action="{{ route('salary.raise.index') }}"
                        class="d-flex align-items-center flex-wrap gap-2">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">

                        <div class="input-group input-group-sm" style="width: 180px;">
                            <span class="input-group-text"><i class="bx bx-calendar-event"></i>&nbsp;Tahun</span>
                            <input type="number" name="tahun" class="form-control" placeholder="YYYY"
                                min="{{ date('Y') - 5 }}" max="{{ date('Y') + 5 }}" value="{{ $tahun ?? '' }}">
                        </div>

                        @if (in_array($me->role, ['owner', 'hr']))
                            <div class="input-group input-group-sm" style="width: 240px;">
                                <span class="input-group-text"><i class="bx bx-buildings"></i>&nbsp;Divisi</span>
                                <select name="division_id" class="form-select">
                                    <option value="" {{ empty($division_id) ? 'selected' : '' }}>Semua Divisi
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

                        <div class="input-group input-group-sm" style="width: 200px;">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control"
                                placeholder="Cari karyawan...">
                        </div>

                        <button class="btn btn-secondary btn-sm" type="submit">
                            <i class="bx bx-filter-alt me-1"></i> Filter
                        </button>

                        <a href="{{ route('salary.raise.index', ['per_page' => $perPage]) }}" class="btn btn-light btn-sm">
                            <i class="bx bx-reset me-1"></i> Reset
                        </a>
                    </form>
                </div>

                {{-- Info bobot global --}}
                <div class="mt-2">
                    @if (empty($hasGlobalWeights))
                        <span class="badge bg-label-warning">
                            Bobot AHP Global belum diatur — pakai default 1/3 : 1/3 : 1/3
                        </span>
                    @else
                        <small class="text-muted">
                            Bobot Global · KPI Umum: {{ round($weights[0] * 100) }}%,
                            KPI Divisi: {{ round($weights[1] * 100) }}%,
                            Peer: {{ round($weights[2] * 100) }}%
                        </small>
                    @endif
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
                                <th>TAHUN</th>
                                <th class="text-end">SKOR AKHIR TAHUNAN</th>
                                <th>LABEL</th>
                                <th class="text-end">REKOMENDASI (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (is_null($tahun))
                                <tr>
                                    <td colspan="7" class="text-muted py-4 text-center">
                                        Silakan pilih <strong>Tahun</strong> terlebih dahulu.
                                    </td>
                                </tr>
                            @else
                                @forelse ($users as $i=>$u)
                                    @php $r = $rows[$u->id] ?? null; @endphp
                                    <tr>
                                        <td>{{ ($users->currentPage() - 1) * $users->perPage() + $i + 1 }}</td>
                                        <td class="fw-semibold">{{ $u->full_name }}</td>
                                        <td>{{ $u->division?->name ?? '-' }}</td>
                                        <td>{{ $tahun }}</td>
                                        <td class="text-end">
                                            @if ($r)
                                                {{ rtrim(rtrim(number_format($r['final'], 2, '.', ''), '0'), '.') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if ($r)
                                                <span
                                                    class="badge {{ $r['label'] === 'Sangat Baik' ? 'bg-success' : ($r['label'] === 'Baik' ? 'bg-primary' : 'bg-secondary') }}">
                                                    {{ $r['label'] }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($r)
                                                {{ $r['range'] }}
                                            @else
                                                -
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
                    $from = !is_null($tahun) && $users->count() ? $users->firstItem() : 0;
                    $to = !is_null($tahun) && $users->count() ? $users->lastItem() : 0;
                    $total = !is_null($tahun) ? $users->total() : 0;
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $total }}
                        entries</small>
                    @if (!is_null($tahun) && $users->hasPages())
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
