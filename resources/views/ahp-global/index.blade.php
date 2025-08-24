@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Pembobotan AHP — Global (KPI Umum, KPI Divisi, Penilaian Karyawan)</h5>
            </div>

            <div class="card-body">
                {{-- Flash/Toast fallback --}}
                @if (session('success'))
                    <div class="alert alert-success mb-3">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                @endif

                {{-- Ringkasan bobot tersimpan (jika ada) --}}
                @if ($existing)
                    <div class="mb-3">
                        <div class="small text-muted">Bobot global tersimpan saat ini:</div>
                        <ul class="mb-0">
                            <li>KPI Umum:
                                <strong>{{ rtrim(rtrim(number_format($existing->w_kpi_umum * 100, 2, '.', ''), '0'), '.') }}%</strong>
                            </li>
                            <li>KPI Divisi:
                                <strong>{{ rtrim(rtrim(number_format($existing->w_kpi_divisi * 100, 2, '.', ''), '0'), '.') }}%</strong>
                            </li>
                            <li>Penilaian Karyawan (Peer):
                                <strong>{{ rtrim(rtrim(number_format($existing->w_peer * 100, 2, '.', ''), '0'), '.') }}%</strong>
                            </li>
                            <li>CR: <strong>{{ rtrim(rtrim(number_format($existing->cr, 4, '.', ''), '0'), '.') }}</strong>
                            </li>
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('ahp.global.hitung') }}">
                    @csrf



                    <div class="table-responsive"
                        style="white-space: normal; overflow-x:hidden; overflow-y:auto; max-height:65vh;">
                        <table class="table-hover table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40%">Kriteria 1</th>
                                    <th class="text-center" style="width:20%">Skala Saaty</th>
                                    <th class="text-end" style="width:40%">Kriteria 2</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pairs as [$c1, $c2])
                                    @php $field = "pair_{$c1}_{$c2}"; @endphp
                                    <tr>
                                        <td class="fw-semibold">
                                            {{ $criteria[$c1] }}

                                        </td>
                                        <td class="text-center">
                                            <select class="form-select" name="{{ $field }}" required
                                                style="min-width:120px;">
                                                @foreach ($saatyOptions as $val => $label)
                                                    <option value="{{ $val }}" @selected(old($field) == $val)>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error($field)
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td class="fw-semibold text-end">{{ $criteria[$c2] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        @if ($me->role === 'hr')
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-calculator me-1"></i> Hitung
                            </button>
                        @else
                            <button type="button" class="btn btn-secondary" disabled
                                title="Hanya HR yang dapat menyimpan.">
                                <i class="bx bx-lock-alt me-1"></i> Hanya HR yang dapat menyimpan
                            </button>
                        @endif
                    </div>
                </form>
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
        @php
            $maxErr = 3;
        @endphp
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
            @foreach ($errors->take($maxErr) as $err)
                Toast.fire({
                    icon: 'error',
                    title: @json($err)
                });
            @endforeach
        @endif
    </script>
@endpush
