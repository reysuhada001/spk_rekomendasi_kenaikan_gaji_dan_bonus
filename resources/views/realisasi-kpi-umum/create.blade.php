@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Input Realisasi — {{ $user->full_name }} ({{ $bulan }}/{{ $tahun }})</h5>
                <a href="{{ route('realisasi-kpi-umum.index', ['bulan' => $bulan, 'tahun' => $tahun]) }}"
                    class="btn btn-sm btn-outline-secondary">Kembali</a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('realisasi-kpi-umum.store', $user->id) }}">
                    @csrf
                    <input type="hidden" name="bulan" value="{{ $bulan }}">
                    <input type="hidden" name="tahun" value="{{ $tahun }}">

                    <div class="table-responsive" style="overflow-y:auto; max-height:65vh;">
                        <table class="table-hover table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>NAMA KPI</th>
                                    <th>TIPE</th>
                                    <th>SATUAN</th>
                                    <th class="text-end">TARGET</th>
                                    <th class="text-end" style="width:180px;">REALISASI</th>
                                    <th class="text-end">SKOR (LIVE)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($kpis as $k)
                                    @php
                                        $ex = optional($real?->items->firstWhere('kpi_umum_id', $k->id));
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $k->nama }}</td>
                                        <td class="text-uppercase">{{ $k->tipe }}</td>
                                        <td>{{ $k->satuan ?? '-' }}</td>
                                        <td class="text-end">
                                            {{ rtrim(rtrim(number_format($k->target, 2, '.', ''), '0'), '.') }}</td>
                                        <td class="text-end">
                                            <input type="number" step="0.01"
                                                class="form-control form-control-sm js-real text-end"
                                                name="items[{{ $k->id }}][realisasi]"
                                                data-tipe="{{ $k->tipe }}" data-target="{{ (float) $k->target }}"
                                                value="{{ $ex?->realisasi ?? 0 }}" required>
                                        </td>
                                        <td class="js-score text-end">0</td>
                                    </tr>
                                    <input type="hidden" name="items[{{ $k->id }}][kpi_id]"
                                        value="{{ $k->id }}">
                                    <input type="hidden" name="items[{{ $k->id }}][tipe]"
                                        value="{{ $k->tipe }}">
                                    <input type="hidden" name="items[{{ $k->id }}][satuan]"
                                        value="{{ $k->satuan }}">
                                    <input type="hidden" name="items[{{ $k->id }}][target]"
                                        value="{{ (float) $k->target }}">
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button class="btn btn-primary" type="submit"><i class="bx bx-send me-1"></i> Simpan
                            (Ajukan)</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // === KONSTANTA harus sama dengan backend ===
        const CAP_MAX = 200.0; // sama dgn controller

        // === Triangular membership μ(x; a,b,c) ===
        function tri(x, a, b, c) {
            if (x <= a || x >= c) return 0.0;
            if (x === b) return 1.0;
            if (x < b) return (x - a) / Math.max(1e-9, (b - a));
            return (c - x) / Math.max(1e-9, (c - b));
        }

        // === Fuzzy utk Kuantitatif/Kualitatif/Response (anchor 50/80/95) ===
        function fuzzyGeneral(r) {
            const muL = tri(r, 0.0, 0.3, 0.6); // L
            const muM = tri(r, 0.4, 0.7, 1.0); // M
            const muH = tri(r, 0.6, 0.9, 1.0); // H
            const num = muL * 50 + muM * 80 + muH * 95;
            const den = muL + muM + muH;
            return den > 0 ? (num / den) : 50.0;
        }

        // === Fuzzy utk Persentase (anchor 60/85/98) ===
        function fuzzyPercent(r) {
            const muL = tri(r, 0.0, 0.3, 0.6); // L
            const muM = tri(r, 0.4, 0.6, 0.8); // M
            const muH = tri(r, 0.7, 1.0, 1.0); // H (puncak di 1.0)
            const num = muL * 60 + muM * 85 + muH * 98;
            const den = muL + muM + muH;
            return den > 0 ? (num / den) : 60.0;
        }

        // === Skor per KPI (identik dengan backend) ===
        function scorePerKpi(tipe, target, realisasi) {
            const t = parseFloat(target || 0);
            const r = parseFloat(realisasi || 0);

            if (tipe === 'response') {
                if (r <= 0) return 0;
                const y = t / r; // lebih cepat = lebih baik
                return (y >= 1) ? Math.min(CAP_MAX, 100 * y) : fuzzyGeneral(y);
            }

            if (t <= 0) return 0; // selain response butuh target > 0
            const x = r / t; // lebih besar = lebih baik

            if (x >= 1) return Math.min(CAP_MAX, 100 * x);
            if (tipe === 'persentase') return fuzzyPercent(x);
            return fuzzyGeneral(x); // kuantitatif & kualitatif
        }

        function fmt(x) {
            return (Math.round(x * 100) / 100).toString(); // 2 desimal
        }

        function recalcRow(tr) {
            const inp = tr.querySelector('.js-real');
            const tipe = inp.getAttribute('data-tipe');
            const target = parseFloat(inp.getAttribute('data-target'));
            const real = parseFloat(inp.value || 0);
            const s = scorePerKpi(tipe, target, real);
            tr.querySelector('.js-score').textContent = fmt(s);
        }

        document.querySelectorAll('.js-real').forEach(el => {
            const tr = el.closest('tr');
            recalcRow(tr);
            el.addEventListener('input', () => recalcRow(tr));
            el.addEventListener('change', () => recalcRow(tr));
        });

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
    </script>
@endpush
