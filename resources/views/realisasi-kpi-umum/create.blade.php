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
        // Fuzzy helper di front-end (sinkron dgn backend)
        function clamp(x, a, b) {
            return Math.max(a, Math.min(b, x));
        }

        function fuzzyBelowTarget(r) {
            let muLow = (r <= 0.4) ? 1 : (r >= 0.8 ? 0 : (0.8 - r) / 0.4);
            let muNear = (r <= 0.6) ? 0 : (r >= 1.0 ? 1 : (r - 0.6) / 0.4);
            let num = muLow * 60 + muNear * 90,
                den = muLow + muNear;
            return den > 0 ? (num / den) : 50;
        }

        function scorePerKpi(tipe, target, realisasi) {
            target = parseFloat(target || 0);
            realisasi = parseFloat(realisasi || 0);
            if (tipe === 'response') {
                if (realisasi <= 0) return 0;
                let ratio = target / realisasi;
                if (ratio >= 1) return 100 * ratio; // lebih cepat / sama
                return fuzzyBelowTarget(ratio);
            } else {
                if (target <= 0) return 0;
                let ratio = realisasi / target;
                if (ratio >= 1) return 100 * ratio; // di atas / sama
                return fuzzyBelowTarget(ratio);
            }
        }

        function fmt(x) {
            return (Math.round(x * 100) / 100).toString();
        } // 2 desimal max

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
