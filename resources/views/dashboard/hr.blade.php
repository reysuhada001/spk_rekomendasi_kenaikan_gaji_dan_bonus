@extends('layouts.app')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">

        @php $periodeText = ($bulanList[$bulan] ?? $bulan).' '.$tahun; @endphp

        {{-- Row 1: 2 Card --}}
        <div class="row g-4">
            {{-- Card 1: Top 5 Global --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="me-2">
                            <h6 class="card-title d-flex align-items-center mb-1"><i class="bx bx-trophy me-1"></i> Top 5
                                Karyawan Global</h6>
                        </div>
                        <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2">
                            <select name="bulan" class="form-select form-select-sm w-auto">
                                @foreach ($bulanList as $num => $label)
                                    <option value="{{ $num }}" {{ (int) $bulan === (int) $num ? 'selected' : '' }}>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="tahun" value="{{ $tahun }}"
                                class="form-control form-control-sm w-auto" style="width:90px" min="2000"
                                max="2100">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bx bx-filter"></i></button>
                        </form>
                    </div>
                    <div class="card-body pb-2">
                        <div class="overflow-auto" style="max-height:260px;">
                            <table class="table-sm mb-0 table align-middle">
                                <thead>
                                    <tr class="text-muted text-uppercase small">
                                        <th style="width:56px;">Rank</th>
                                        <th>Nama</th>
                                        <th>Divisi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($topGlobal as $row)
                                        <tr>
                                            <td class="fw-semibold">{{ $row['rank'] }}</td>
                                            <td class="fw-semibold">{{ $row['name'] }}</td>
                                            <td>{{ $row['division'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-muted py-4 text-center">Belum ada data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">Periode: {{ $periodeText }}</div>
                </div>
            </div>

            {{-- Card 2: Top 5 Karyawan di Divisi --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="me-2">
                            <h6 class="card-title d-flex align-items-center mb-1"><i class="bx bx-target-lock me-1"></i> Top
                                5 Karyawan di Divisi</h6>
                        </div>
                        <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2">
                            <select name="division_id" class="form-select form-select-sm w-120px" style="min-width:100px;">
                                @foreach ($divisions as $d)
                                    <option value="{{ $d->id }}"
                                        {{ (int) $divisionId === (int) $d->id ? 'selected' : '' }}>{{ $d->name }}
                                    </option>
                                @endforeach
                            </select>
                            <select name="bulan" class="form-select form-select-sm w-auto">
                                @foreach ($bulanList as $num => $label)
                                    <option value="{{ $num }}"
                                        {{ (int) $bulan === (int) $num ? 'selected' : '' }}>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="tahun" value="{{ $tahun }}"
                                class="form-control form-control-sm w-auto" style="width:90px" min="2000"
                                max="2100">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bx bx-filter"></i></button>
                        </form>
                    </div>
                    <div class="card-body pb-2">
                        @if (empty($divisionId))
                            <div class="text-muted py-5 text-center">Silakan pilih divisi terlebih dahulu.</div>
                        @else
                            <div class="overflow-auto" style="max-height:260px;">
                                <table class="table-sm mb-0 table align-middle">
                                    <thead>
                                        <tr class="text-muted text-uppercase small">
                                            <th style="width:56px;">Rank</th>
                                            <th>Nama</th>
                                            <th class="text-end" style="width:90px;">Skor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($topInDivisi as $row)
                                            <tr>
                                                <td class="fw-semibold">{{ $row['rank'] }}</td>
                                                <td class="fw-semibold">{{ $row['name'] }}</td>
                                                <td class="text-end">{{ number_format($row['score'], 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-muted py-4 text-center">Belum ada data.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer small text-muted">Periode: {{ $periodeText }}</div>
                </div>
            </div>
        </div>

        {{-- Row 2: 1 Card --}}
        <div class="row g-4 mt-1">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="me-2">
                            <h6 class="card-title d-flex align-items-center mb-1"><i class="bx bx-building-house me-1"></i>
                                Top 5 Divisi (KPI Divisi)</h6>
                        </div>
                        <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2">
                            <select name="bulan" class="form-select form-select-sm w-auto">
                                @foreach ($bulanList as $num => $label)
                                    <option value="{{ $num }}"
                                        {{ (int) $bulan === (int) $num ? 'selected' : '' }}>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="tahun" value="{{ $tahun }}"
                                class="form-control form-control-sm w-auto" style="width:90px" min="2000"
                                max="2100">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bx bx-filter"></i></button>
                        </form>
                    </div>
                    <div class="card-body pb-2">
                        <div class="overflow-auto" style="max-height:260px;">
                            <table class="table-sm mb-0 table align-middle">
                                <thead>
                                    <tr class="text-muted text-uppercase small">
                                        <th style="width:56px;">Rank</th>
                                        <th>Divisi</th>
                                        <th class="text-end" style="width:90px;">Avg</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($topDivisi as $row)
                                        <tr>
                                            <td class="fw-semibold">{{ $row['rank'] }}</td>
                                            <td class="fw-semibold">{{ $row['division'] }}</td>
                                            <td class="text-end">{{ number_format($row['score'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-muted py-4 text-center">Belum ada data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">Periode: {{ $periodeText }}</div>
                </div>
            </div>
        </div>

    </div>
@endsection
