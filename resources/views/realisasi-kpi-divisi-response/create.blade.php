@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Input Realisasi (Response) — {{ $user->full_name }} • {{ $bulanList[$bulan] }}
                    {{ $tahun }}</h5>
                <a href="{{ route('realisasi-kpi-divisi-response.index', ['bulan' => $bulan, 'tahun' => $tahun, 'division_id' => $user->division_id]) }}"
                    class="btn btn-sm btn-outline-secondary">Kembali</a>
            </div>

            <div class="card-body">
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('realisasi-kpi-divisi-response.store') }}">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                    <input type="hidden" name="bulan" value="{{ $bulan }}">
                    <input type="hidden" name="tahun" value="{{ $tahun }}">

                    <div class="table-responsive" style="max-height:65vh; overflow:auto; white-space:nowrap;">
                        <table class="table-hover table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:280px;">Nama KPI</th>
                                    <th class="text-end" style="width:160px;">Target (lebih kecil lebih baik)</th>
                                    <th class="text-end" style="width:180px;">Realisasi</th>
                                    <th class="text-end" style="width:160px;">Skor (live)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($kpis as $k)
                                    @php $ex = $existing[$k->id]['realization'] ?? ''; @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $k->nama }}</td>
                                        <td class="text-end">
                                            {{ rtrim(rtrim(number_format($k->target, 2, '.', ''), '0'), '.') }}
                                            {{ $k->satuan }}
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="real[{{ $k->id }}]"
                                                value="{{ $ex }}" class="form-control js-real text-end"
                                                data-target="{{ (float) $k->target }}">
                                        </td>
                                        <td class="text-end"><span class="badge bg-label-primary js-score">0</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary"><i class="bx bx-send me-1"></i> Ajukan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function calcScoreResponse(r, t) {
            const eps = 1e-9;
            r = parseFloat(r || 0);
            t = parseFloat(t || 0);
            if (t <= 0) return r <= 0 ? 100 : 50;
            if (r <= t) {
                return Math.round((100 * (t / Math.max(r, eps))) * 100) / 100; // >= 100
            }
            // r > t: fuzzy menurun berdasarkan x = t/r ∈ (0,1)
            const x = Math.max(0, Math.min(1, t / Math.max(r, eps)));
            // near (0.7..1), med (0.4..0.8), far (<=0.6)
            let muN = 0,
                muM = 0,
                muF = 0;
            if (x > 0.7 && x <= 1.0) muN = (x - 0.7) / (1.0 - 0.7 + 1e-9);
            if (x > 0.4 && x <= 0.6) muM = (x - 0.4) / (0.6 - 0.4 + 1e-9);
            else if (x > 0.6 && x <= 0.8) muM = (0.8 - x) / (0.8 - 0.6 + 1e-9);
            if (x <= 0.3) muF = 1;
            else if (x > 0.3 && x <= 0.6) muF = (0.6 - x) / (0.6 - 0.3 + 1e-9);
            const wN = 95,
                wM = 80,
                wF = 50;
            const den = muN + muM + muF;
            if (den <= 0) return 50;
            const s = (muN * wN + muM * wM + muF * wF) / den;
            return Math.round(s * 100) / 100;
        }

        function refresh() {
            document.querySelectorAll('.js-real').forEach(inp => {
                const t = parseFloat(inp.dataset.target || 0);
                const s = calcScoreResponse(inp.value, t);
                inp.closest('tr').querySelector('.js-score').textContent = s;
            });
        }
        document.querySelectorAll('.js-real').forEach(i => i.addEventListener('input', refresh));
        refresh();
    </script>
@endpush
