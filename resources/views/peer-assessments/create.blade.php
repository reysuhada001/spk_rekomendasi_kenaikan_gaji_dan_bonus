@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Penilaian Rekan Satu Divisi</h5>
                    <small class="text-muted">
                        Dinilai: <strong>{{ $assessee->full_name }}</strong> — {{ $assessee->division?->name ?? '-' }}
                        • Periode {{ $bulanList[$bulan] }} {{ $tahun }}
                    </small>
                </div>
                <a href="{{ route('peer.index', ['bulan' => $bulan, 'tahun' => $tahun]) }}"
                    class="btn btn-sm btn-outline-secondary">Kembali</a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('peer.store') }}">
                    @csrf
                    <input type="hidden" name="assessee_id" value="{{ $assessee->id }}">
                    <input type="hidden" name="bulan" value="{{ $bulan }}">
                    <input type="hidden" name="tahun" value="{{ $tahun }}">

                    <div class="table-responsive" style="white-space:nowrap;max-height:65vh;overflow:auto;">
                        <table class="table-hover table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:320px;">Aspek</th>
                                    <th style="width:240px;">Skor (1–10)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($aspeks as $a)
                                    <tr>
                                        <td class="fw-semibold">{{ $a->nama }}</td>
                                        <td>
                                            <select name="score[{{ $a->id }}]" class="form-select" required>
                                                <option value="" selected disabled>Pilih skor</option>
                                                @foreach ($scale as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-send me-1"></i> Kirim Penilaian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
