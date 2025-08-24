@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Detail Penilaian</h5>
                    <small class="text-muted">
                        {{ $user->full_name }} — {{ $user->division?->name ?? '-' }} |
                        Periode: {{ $bulanList[$bulan] ?? $bulan }} {{ $tahun }}
                    </small>
                </div>
                <a href="{{ route('peer.admin.index', ['bulan' => $bulan, 'tahun' => $tahun, 'division_id' => request('division_id')]) }}"
                    class="btn btn-light btn-sm">
                    <i class="bx bx-left-arrow-alt me-1"></i>Kembali
                </a>
            </div>

            <div class="card-body">
                @if ($assessments->isEmpty())
                    <div class="alert alert-info">Belum ada penilaian yang masuk.</div>
                @else
                    <h6 class="mb-2">Nilai per Penilai</h6>
                    <div class="table-responsive mb-4" style="white-space:nowrap;">
                        <table class="table-sm table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:220px">Penilai</th>
                                    @foreach ($columns as $c)
                                        <th class="text-center">{{ $c['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assessments as $a)
                                    @php $row = $itemsMatrix[$a->id] ?? []; @endphp
                                    <tr>
                                        <td>{{ $a->assessor?->full_name ?? '#' . $a->assessor_id }}</td>
                                        @foreach ($columns as $c)
                                            @php $sc = $row[$c['key']] ?? null; @endphp
                                            <td class="text-center">{{ $sc !== null ? $sc : '-' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mb-2">Rata-rata per Aspek</h6>
                    <div class="table-responsive" style="white-space:nowrap;">
                        <table class="table-sm table align-middle">
                            <thead class="table-light">
                                <tr>
                                    @foreach ($columns as $c)
                                        <th class="text-center">{{ $c['label'] }}</th>
                                    @endforeach
                                    <th class="text-center">Rata-rata Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    @foreach ($columns as $c)
                                        @php $val = $avgPerKey[$c['key']] ?? null; @endphp
                                        <td class="text-center">
                                            {{ $val !== null ? rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.') : '-' }}
                                        </td>
                                    @endforeach
                                    <td class="text-center">
                                        {{ $avgTotal !== null ? rtrim(rtrim(number_format($avgTotal, 2, '.', ''), '0'), '.') : '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
