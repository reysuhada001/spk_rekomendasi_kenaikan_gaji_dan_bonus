@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Input Realisasi (Persentase) — {{ $kpi->nama }}</h5>
                <a href="{{ route('realisasi-kpi-divisi-persentase.index', ['bulan' => $kpi->bulan, 'tahun' => $kpi->tahun, 'division_id' => $kpi->division_id]) }}"
                    class="btn btn-sm btn-outline-secondary">Kembali</a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('realisasi-kpi-divisi-persentase.store') }}">
                    @csrf
                    <input type="hidden" name="kpi_divisi_id" value="{{ $kpi->id }}">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Divisi</label>
                            <input type="text" class="form-control" value="{{ $kpi->division?->name }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Periode</label>
                            <input type="text" class="form-control"
                                value="{{ $bulanList[$kpi->bulan] }} {{ $kpi->tahun }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Satuan</label>
                            <input type="text" class="form-control" value="{{ $kpi->satuan }}" disabled>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Target</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-end"
                                    value="{{ rtrim(rtrim(number_format($kpi->target, 2, '.', ''), '0'), '.') }}" disabled>
                                <span class="input-group-text">{{ $kpi->satuan }}</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Realisasi</label>
                            <div class="input-group">
                                <input id="real" name="realization" type="number" step="0.01"
                                    class="form-control text-end" value="{{ $existing->realization ?? '' }}" required>
                                <span class="input-group-text">{{ $kpi->satuan }}</span>
                            </div>
                            <small class="text-muted">Skor (live): <span id="liveScore"
                                    class="fw-semibold">0</span>%</small>
                        </div>
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
        const target = {{ (float) $kpi->target }};

        // Preview harus match backend: CAP 200 ketika real >= target
        function fuzzyScorePercentage(real, t) {
            const eps = 1e-9;
            real = parseFloat(real || 0);
            t = parseFloat(t || 0);

            if (t <= 0) return real <= 0 ? 100 : Math.min(150, 200);

            if (real >= t) {
                const lin = 100 * (real / Math.max(t, eps));
                return Math.round(Math.min(lin, 200) * 100) / 100; // CAP 200
            }

            const x = Math.max(0, Math.min(1, real / Math.max(t, eps)));
            let muL = 0,
                muM = 0,
                muH = 0;
            if (x <= 0.3) muL = 1;
            else if (x <= 0.6) muL = (0.6 - x) / (0.6 - 0.3 + 1e-9);

            if (x > 0.4 && x <= 0.6) muM = (x - 0.4) / (0.6 - 0.4 + 1e-9);
            else if (x > 0.6 && x <= 0.8) muM = (0.8 - x) / (0.8 - 0.6 + 1e-9);

            if (x > 0.7 && x <= 1.0) muH = (x - 0.7) / (1.0 - 0.7 + 1e-9);

            const wL = 60,
                wM = 85,
                wH = 98,
                den = muL + muM + muH;
            if (den <= 0) return 60;
            return Math.round(((muL * wL + muM * wM + muH * wH) / den) * 100) / 100;
        }

        function refresh() {
            const r = document.getElementById('real').value;
            document.getElementById('liveScore').textContent = fuzzyScorePercentage(r, target);
        }
        document.getElementById('real').addEventListener('input', refresh);
        refresh();
    </script>
@endpush
