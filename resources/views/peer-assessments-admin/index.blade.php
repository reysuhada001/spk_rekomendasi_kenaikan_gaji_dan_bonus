@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">Rekap Penilaian Antar Karyawan (HR)</h5>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    {{-- Per Page (kiri) --}}
                    <form method="GET" action="{{ route('peer.admin.index') }}" class="d-flex align-items-center gap-2">
                        {{-- persist filter utama --}}
                        <input type="hidden" name="bulan" value="{{ $bulan }}">
                        <input type="hidden" name="tahun" value="{{ $tahun }}">
                        <input type="hidden" name="division_id" value="{{ $division_id }}">
                        <input type="hidden" name="search" value="{{ $search }}">

                        <label class="small text-muted mb-0">Show</label>
                        <div class="input-group input-group-sm" style="width:100px;">
                            <select name="per_page" class="form-select" onchange="this.form.submit()">
                                @foreach ([10, 25, 50, 75, 100] as $pp)
                                    <option value="{{ $pp }}"
                                        {{ (int) request('per_page', 10) === $pp ? 'selected' : '' }}>
                                        {{ $pp }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <span class="small text-muted">entries</span>
                    </form>

                    {{-- Filter (kanan) --}}
                    <form method="GET" action="{{ route('peer.admin.index') }}"
                        class="d-flex align-items-center flex-wrap gap-2">
                        {{-- persist per_page saat filter --}}
                        <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">

                        <div class="input-group input-group-sm" style="width:200px;">
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

                        <div class="input-group input-group-sm" style="width:180px;">
                            <span class="input-group-text"><i class="bx bx-calendar-event"></i>&nbsp;Tahun</span>
                            <input type="number" name="tahun" class="form-control" placeholder="YYYY"
                                min="{{ date('Y') - 5 }}" max="{{ date('Y') + 5 }}" value="{{ $tahun ?? '' }}">
                        </div>

                        <div class="input-group input-group-sm" style="width:240px;">
                            <span class="input-group-text"><i class="bx bx-buildings"></i>&nbsp;Divisi</span>
                            <select name="division_id" class="form-select">
                                <option value="" {{ empty($division_id) ? 'selected' : '' }}>Semua Divisi</option>
                                @foreach ($divisions as $d)
                                    <option value="{{ $d->id }}"
                                        {{ (string) $division_id === (string) $d->id ? 'selected' : '' }}>
                                        {{ $d->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="input-group input-group-sm" style="width:260px;">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control"
                                placeholder="Cari karyawan...">
                        </div>

                        <button class="btn btn-secondary btn-sm" type="submit">
                            <i class="bx bx-filter-alt me-1"></i> Filter
                        </button>

                        <a href="{{ route('peer.admin.index', ['per_page' => request('per_page', 10)]) }}"
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
                                <th>Nama</th>
                                <th>Divisi</th>
                                <th>Bulan</th>
                                <th>Tahun</th>
                                <th class="text-end">Rata-rata</th>
                                <th style="width:140px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (is_null($bulan) || is_null($tahun))
                                <tr>
                                    <td colspan="7" class="text-muted py-4 text-center">Pilih Bulan & Tahun.</td>
                                </tr>
                            @else
                                @forelse($users as $i=>$u)
                                    @php $avg = $avgByUser[$u->id] ?? null; @endphp
                                    <tr>
                                        <td>{{ ($users->currentPage() - 1) * $users->perPage() + $i + 1 }}</td>
                                        <td class="fw-semibold">{{ $u->full_name }}</td>
                                        <td>{{ $u->division?->name ?? '-' }}</td>
                                        <td>{{ $bulanList[$bulan] ?? '-' }}</td>
                                        <td>{{ $tahun }}</td>
                                        <td class="text-end">
                                            {{ $avg ? rtrim(rtrim(number_format($avg, 2, '.', ''), '0'), '.') : '-' }}
                                        </td>
                                        <td>
                                            @if ($avg !== null)
                                                <a class="btn btn-icon btn-sm btn-outline-secondary"
                                                    href="{{ route('peer.admin.show', ['user' => $u->id, 'bulan' => $bulan, 'tahun' => $tahun]) }}"
                                                    title="Detail" aria-label="Detail">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
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
