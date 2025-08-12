@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">Leaderboard Antar Divisi — KPI Divisi</h5>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    {{-- Per Page (kiri) --}}
                    <form method="GET" action="{{ route('leaderboard.divisi-kpi.index') }}"
                        class="d-flex align-items-center gap-2">
                        <input type="hidden" name="bulan" value="{{ $bulan }}">
                        <input type="hidden" name="tahun" value="{{ $tahun }}">
                        <label class="small text-muted mb-0">Show</label>
                        <div class="input-group input-group-sm" style="width:100px;">
                            <select name="per_page" class="form-select" onchange="this.form.submit()">
                                @foreach ([10, 25, 50, 75, 100] as $pp)
                                    <option value="{{ $pp }}"
                                        {{ (int) ($perPage ?? 10) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="small text-muted">entries</span>
                    </form>

                    {{-- Filter (kanan) --}}
                    <form method="GET" action="{{ route('leaderboard.divisi-kpi.index') }}"
                        class="d-flex align-items-center flex-wrap gap-2">
                        <input type="hidden" name="per_page" value="{{ $perPage ?? 10 }}">

                        <div class="input-group input-group-sm" style="width:200px;">
                            <span class="input-group-text"><i class="bx bx-calendar"></i>&nbsp;Bulan</span>
                            <select name="bulan" class="form-select">
                                <option value="" {{ is_null($bulan) ? 'selected' : '' }}>Pilih Bulan</option>
                                @foreach ($bulanList as $num => $label)
                                    <option value="{{ $num }}"
                                        {{ (string) $bulan === (string) $num ? 'selected' : '' }}>{{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="input-group input-group-sm" style="width:180px;">
                            <span class="input-group-text"><i class="bx bx-calendar-event"></i>&nbsp;Tahun</span>
                            <input type="number" name="tahun" class="form-control" placeholder="YYYY"
                                min="{{ date('Y') - 5 }}" max="{{ date('Y') + 5 }}" value="{{ $tahun ?? '' }}">
                        </div>

                        <button class="btn btn-secondary btn-sm" type="submit">
                            <i class="bx bx-filter-alt me-1"></i> Filter
                        </button>
                        <a href="{{ route('leaderboard.divisi-kpi.index', ['per_page' => $perPage ?? 10]) }}"
                            class="btn btn-light btn-sm">
                            <i class="bx bx-reset me-1"></i> Reset
                        </a>
                    </form>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive" style="white-space:nowrap; overflow:auto; max-height:65vh;">
                    <table class="table-hover table align-middle">
                        <thead>
                            <tr>
                                <th>DIVISI</th>
                                <th style="width:100px" class="text-center">RANK</th>
                                <th class="text-end" style="width:220px">JUMLAH KARYAWAN DINILAI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (is_null($bulan) || is_null($tahun))
                                <tr>
                                    <td colspan="3" class="text-muted py-4 text-center">
                                        Silakan pilih <strong>Bulan</strong> & <strong>Tahun</strong>.
                                    </td>
                                </tr>
                            @else
                                @forelse ($rows as $r)
                                    <tr>
                                        <td class="fw-semibold">{{ $r['division'] }}</td>
                                        <td class="text-center">{{ $r['rank'] }}</td>
                                        <td class="text-end">{{ $r['n_users'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-muted py-4 text-center">Tidak ada data.</td>
                                    </tr>
                                @endforelse
                            @endif
                        </tbody>
                    </table>
                </div>

                @php
                    $from = $rows->count() ? $rows->firstItem() : 0;
                    $to = $rows->count() ? $rows->lastItem() : 0;
                    $tot = $rows->total();
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $tot }}
                        entries</small>
                    @if ($rows->hasPages())
                        {{ $rows->onEachSide(1)->links() }}
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
