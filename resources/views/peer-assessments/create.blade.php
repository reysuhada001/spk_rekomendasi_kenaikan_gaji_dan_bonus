@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Input Nilai Rekan</h5>
                    <small class="text-muted">
                        {{ $assessee->full_name }} — {{ $assessee->division?->name ?? '-' }} |
                        Periode: {{ $bulanList[$bulan] ?? $bulan }} {{ $tahun }}
                    </small>
                </div>
                <a href="{{ route('peer.index', ['bulan' => $bulan, 'tahun' => $tahun]) }}" class="btn btn-light btn-sm">
                    <i class="bx bx-left-arrow-alt me-1"></i> Kembali
                </a>
            </div>

            <form method="POST" action="{{ route('peer.store') }}" class="card-body">
                @csrf
                <input type="hidden" name="assessee_id" value="{{ $assessee->id }}">
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">

                @if (session('error'))
                    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                @endif

                <div class="table-responsive" style="white-space:normal;overflow-x:hidden;overflow-y:auto;max-height:65vh;">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:55%">Aspek</th>
                                <th style="width:45%">Nilai (1–10)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($aspeks as $a)
                                <tr>
                                    <td class="fw-semibold">{{ $a->nama }}</td>
                                    <td>
                                        <select name="score[{{ $a->id }}]" class="form-select" required>
                                            @foreach ($scale as $val => $label)
                                                <option value="{{ $val }}"
                                                    {{ isset($existing[$a->id]) && (int) $existing[$a->id] === $val ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-primary"><i class="bx bx-save me-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection
