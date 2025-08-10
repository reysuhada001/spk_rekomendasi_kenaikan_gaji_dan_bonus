@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">

            {{-- Header + Filter --}}
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Pembobotan AHP — KPI Umum</h5>

                {{-- Filter Bulan & Tahun --}}
                <form method="GET" action="{{ route('ahp.kpi-umum.index') }}" class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 170px;">
                        <span class="input-group-text">Bulan</span>
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

                    <div class="input-group input-group-sm" style="width: 170px;">
                        <span class="input-group-text">Tahun</span>
                        <select name="tahun" class="form-select">
                            <option value="" {{ is_null($tahun) ? 'selected' : '' }}>Pilih Tahun</option>
                            @foreach ($tahunList as $th)
                                <option value="{{ $th }}"
                                    {{ (string) $tahun === (string) $th ? 'selected' : '' }}>
                                    {{ $th }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button class="btn btn-secondary btn-sm" type="submit">
                        <i class="bx bx-filter-alt me-1"></i> Filter
                    </button>
                </form>
            </div>

            {{-- Body --}}
            <div class="card-body">
                @if (is_null($bulan) || is_null($tahun))
                    {{-- kosongkan kalau belum pilih bulan/tahun --}}
                @elseif ($kpis->count() < 2)
                    <div class="alert alert-warning">
                        Minimal diperlukan <strong>2 KPI</strong> pada {{ $bulanList[$bulan] ?? $bulan }}
                        {{ $tahun }} untuk melakukan pembobotan.
                    </div>
                @else
                    <form method="POST" action="{{ route('ahp.kpi-umum.hitung') }}">
                        @csrf
                        <input type="hidden" name="bulan" value="{{ $bulan }}">
                        <input type="hidden" name="tahun" value="{{ $tahun }}">

                        {{-- Table --}}
                        <div class="table-responsive"
                            style="white-space: normal; overflow-x: hidden; overflow-y: auto; max-height: 65vh;">
                            <table class="table-hover table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 38%;">KPI 1</th>
                                        <th class="text-center" style="width: 24%;">Skala Saaty</th>
                                        <th class="text-end" style="width: 38%;">KPI 2</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pairs as [$k1, $k2])
                                        <tr>
                                            <td class="fw-semibold">{{ $k1->nama }}</td>
                                            <td class="text-center">
                                                <select class="form-select form-select-sm" style="min-width: 120px;"
                                                    name="pair_{{ $k1->id }}_{{ $k2->id }}" required>
                                                    @foreach ($saatyOptions as $val => $label)
                                                        <option value="{{ $val }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="fw-semibold text-end">{{ $k2->nama }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-calculator me-1"></i> Hitung    
                            </button>
                        </div>
                    </form>
                @endif
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
