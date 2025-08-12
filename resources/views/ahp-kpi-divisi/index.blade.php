@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            {{-- Header + Filter (mepet kiri) --}}
            <div class="card-header d-flex align-items-center justify-content-start flex-wrap gap-2">
                <h5 class="mb-0 me-3">Pembobotan AHP — KPI Divisi</h5>

                {{-- Filter di sebelah kiri --}}
                <form method="GET" action="{{ route('ahp.kpi-divisi.index') }}"
                    class="d-flex align-items-center flex-wrap gap-2">
                    <div class="input-group input-group-sm" style="width: 240px;">
                        <span class="input-group-text"><i class="bx bx-buildings"></i>&nbsp;Divisi</span>
                        <select name="division_id" class="form-select">
                            <option value="" {{ empty($division_id) ? 'selected' : '' }}>Pilih Divisi</option>
                            @foreach ($divisions as $d)
                                <option value="{{ $d->id }}"
                                    {{ (string) $division_id === (string) $d->id ? 'selected' : '' }}>
                                    {{ $d->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="input-group input-group-sm" style="width: 200px;">
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

                    <div class="input-group input-group-sm" style="width: 180px;">
                        <span class="input-group-text"><i class="bx bx-calendar-event"></i>&nbsp;Tahun</span>
                        <input type="number" name="tahun" class="form-control" placeholder="YYYY"
                            min="{{ date('Y') - 5 }}" max="{{ date('Y') + 5 }}" value="{{ $tahun ?? '' }}">
                    </div>

                    <button class="btn btn-secondary btn-sm" type="submit">
                        <i class="bx bx-filter-alt me-1"></i> Filter
                    </button>

                    <a href="{{ route('ahp.kpi-divisi.index') }}" class="btn btn-light btn-sm">
                        <i class="bx bx-reset me-1"></i> Reset
                    </a>
                </form>
            </div>

            <div class="card-body">
                @if (is_null($division_id) || is_null($bulan) || is_null($tahun))
                    {{-- kosongkan kalau belum pilih filter lengkap --}}
                @elseif ($kpis->count() < 2)
                    <div class="alert alert-warning">
                        Minimal diperlukan <strong>2 KPI</strong> pada periode tersebut.
                    </div>
                @else
                    <form method="POST" action="{{ route('ahp.kpi-divisi.hitung') }}">
                        @csrf
                        <input type="hidden" name="division_id" value="{{ $division_id }}">
                        <input type="hidden" name="bulan" value="{{ $bulan }}">
                        <input type="hidden" name="tahun" value="{{ $tahun }}">

                        <div class="table-responsive"
                            style="white-space: normal; overflow-x:hidden; overflow-y:auto; max-height:65vh;">
                            <table class="table-hover table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:40%">KPI 1</th>
                                        <th class="text-center" style="width:20%">Skala Saaty</th>
                                        <th class="text-end" style="width:40%">KPI 2</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pairs as [$k1, $k2])
                                        <tr>
                                            <td class="fw-semibold">{{ $k1->nama }}</td>
                                            <td class="text-center">
                                                <select class="form-select form-select-sm"
                                                    name="pair_{{ $k1->id }}_{{ $k2->id }}" required
                                                    style="min-width:140px;">
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
                                <i class="bx bx-calculator me-1"></i> Hitung & Simpan Bobot
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
