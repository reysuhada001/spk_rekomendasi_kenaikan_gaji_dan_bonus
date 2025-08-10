@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            {{-- HEADER --}}
            <div class="card-header">
                <h5 class="mb-2">KPI Divisi</h5>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

                    {{-- Show X entries (kiri) --}}
                    <form id="filterForm" method="GET" action="{{ route('kpi-divisi.index') }}"
                        class="d-flex align-items-center gap-2">
                        <span class="small text-muted">Show</span>
                        <div class="input-group input-group-sm" style="width: 110px;">
                            <select name="per_page" class="form-select"
                                onchange="document.getElementById('filterForm').submit()">
                                @foreach ([10, 25, 50, 75, 100] as $pp)
                                    <option value="{{ $pp }}" {{ (int) $perPage === $pp ? 'selected' : '' }}>
                                        {{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="small text-muted">entries</span>

                        {{-- keep existing params on per_page change --}}
                        <input type="hidden" name="search" value="{{ $search }}">
                    </form>

                    {{-- Search + Add (kanan) --}}
                    <div class="d-flex align-items-center ms-auto gap-2">
                        <form method="GET" action="{{ route('kpi-divisi.index') }}"
                            class="d-flex align-items-center gap-2">
                            <div class="input-group input-group-sm" style="width: 240px;">
                                <span class="input-group-text"><i class="bx bx-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search..."
                                    value="{{ $search }}">
                            </div>
                            {{-- keep per_page while searching --}}
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <button class="btn btn-secondary btn-sm" type="submit">Cari</button>
                        </form>

                        @if ($me->role === 'hr')
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
                                <i class="bx bx-plus me-1"></i> Add
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- BODY --}}
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success mb-3">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                @endif

                <div class="table-responsive" style="white-space: nowrap; overflow:auto; max-height:65vh;">
                    <table class="table-hover table align-middle">
                        <thead>
                            <tr>
                                <th style="width:60px">#</th>
                                <th>DIVISI</th>
                                <th>NAMA KPI</th>
                                <th>TIPE</th>
                                <th>SATUAN</th>
                                <th class="text-end">TARGET</th>
                                <th class="text-end">BOBOT (AHP)</th>
                                <th>BULAN</th>
                                <th>TAHUN</th>
                                @if ($me->role === 'hr')
                                    <th style="width:160px">ACTION</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($kpis as $i => $k)
                                <tr>
                                    <td>{{ ($kpis->currentPage() - 1) * $kpis->perPage() + $i + 1 }}</td>
                                    <td>{{ $k->division?->name ?? '-' }}</td>
                                    <td class="fw-semibold">{{ $k->nama }}</td>
                                    <td class="text-uppercase">{{ $k->tipe }}</td>
                                    <td>{{ $k->satuan ?? '-' }}</td>
                                    <td class="text-end">
                                        {{ rtrim(rtrim(number_format($k->target, 2, '.', ''), '0'), '.') }}</td>
                                    <td class="text-end">
                                        @php
                                            // tampilkan bobot sebagai persen tanpa nol buntut
                                            $bobotPercent = $k->bobot !== null ? round($k->bobot * 100, 0) : null;
                                        @endphp
                                        {{ $bobotPercent !== null ? rtrim(rtrim(number_format($bobotPercent, 0  , '.', ''), '0'), '.') . '%' : '-' }}
                                    </td>
                                    <td>{{ $bulanList[$k->bulan] ?? $k->bulan }}</td>
                                    <td>{{ $k->tahun }}</td>
                                    @if ($me->role === 'hr')
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                                    data-bs-target="#editModal" data-id="{{ $k->id }}"
                                                    data-division_id="{{ $k->division_id }}"
                                                    data-nama="{{ $k->nama }}" data-tipe="{{ $k->tipe }}"
                                                    data-satuan="{{ $k->satuan }}" data-target="{{ $k->target }}"
                                                    data-bulan="{{ $k->bulan }}" data-tahun="{{ $k->tahun }}">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>

                                                <form action="{{ route('kpi-divisi.destroy', $k) }}" method="POST"
                                                    class="js-delete d-inline">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $me->role === 'hr' ? 10 : 9 }}" class="text-muted py-4 text-center">
                                        Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Info + Pagination (selalu tampil minimal 1) --}}
                @php
                    $from = $kpis->count() ? $kpis->firstItem() : 0;
                    $to = $kpis->count() ? $kpis->lastItem() : 0;
                    $total = $kpis->total();
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $total }}
                        entries</small>

                    @if ($kpis->hasPages())
                        {{ $kpis->onEachSide(1)->links() }}
                    @else
                        <nav>
                            <ul class="pagination mb-0">
                                <li class="page-item active"><span class="page-link">1</span></li>
                            </ul>
                        </nav>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Create (HR) --}}
    @if ($me->role === 'hr')
        <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('kpi-divisi.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah KPI Divisi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Divisi</label>
                                <select name="division_id" class="form-select" required>
                                    <option value="">- Pilih Divisi -</option>
                                    @foreach ($divisions as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama KPI</label>
                                <input type="text" name="nama" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tipe KPI</label>
                                <select name="tipe" class="form-select" required>
                                    <option value="kuantitatif">kuantitatif</option>
                                    <option value="kualitatif">kualitatif</option>
                                    <option value="response">response</option>
                                    <option value="persentase">persentase</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Satuan</label>
                                <input type="text" name="satuan" class="form-control" placeholder="cth: Unit / %">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Target</label>
                                <input type="number" step="0.01" name="target" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Bulan</label>
                                <select name="bulan" class="form-select" required>
                                    @foreach ($bulanList as $num => $lbl)
                                        <option value="{{ $num }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tahun</label>
                                <input type="number" name="tahun" value="{{ date('Y') }}" class="form-control"
                                    required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Edit --}}
        <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form id="editForm" class="modal-content" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit KPI Divisi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Divisi</label>
                                <select id="editDivision" name="division_id" class="form-select" required>
                                    @foreach ($divisions as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama KPI</label>
                                <input id="editNama" type="text" name="nama" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tipe KPI</label>
                                <select id="editTipe" name="tipe" class="form-select" required>
                                    <option value="kuantitatif">kuantitatif</option>
                                    <option value="kualitatif">kualitatif</option>
                                    <option value="response">response</option>
                                    <option value="persentase">persentase</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Satuan</label>
                                <input id="editSatuan" type="text" name="satuan" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Target</label>
                                <input id="editTarget" type="number" step="0.01" name="target"
                                    class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Bulan</label>
                                <select id="editBulan" name="bulan" class="form-select" required>
                                    @foreach ($bulanList as $num => $lbl)
                                        <option value="{{ $num }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tahun</label>
                                <input id="editTahun" type="number" name="tahun" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
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

        // konfirmasi hapus
        document.querySelectorAll('form.js-delete').forEach(f => {
            f.addEventListener('submit', e => {
                e.preventDefault();
                Swal.fire({
                        title: 'Hapus data ini?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, hapus',
                        cancelButtonText: 'Batal'
                    })
                    .then(r => {
                        if (r.isConfirmed) f.submit();
                    });
            });
        });

        // isi modal edit
        document.getElementById('editModal')?.addEventListener('show.bs.modal', function(ev) {
            const b = ev.relatedTarget;
            document.getElementById('editForm').action = "{{ url('kpi-divisi') }}/" + b.getAttribute('data-id');
            document.getElementById('editDivision').value = b.getAttribute('data-division_id') ?? '';
            document.getElementById('editNama').value = b.getAttribute('data-nama') ?? '';
            document.getElementById('editTipe').value = b.getAttribute('data-tipe') ?? 'kuantitatif';
            document.getElementById('editSatuan').value = b.getAttribute('data-satuan') ?? '';
            document.getElementById('editTarget').value = b.getAttribute('data-target') ?? 0;
            document.getElementById('editBulan').value = b.getAttribute('data-bulan') ?? 1;
            document.getElementById('editTahun').value = b.getAttribute('data-tahun') ?? (new Date()).getFullYear();
        });
    </script>
@endpush
