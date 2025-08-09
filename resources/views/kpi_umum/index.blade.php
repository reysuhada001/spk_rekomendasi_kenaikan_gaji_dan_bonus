@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">KPI Umum</h5>

                <div class="d-flex align-items-center justify-content-between gap-2">
                    {{-- Per Page (kiri) --}}
                    <form id="filterForm" method="GET" action="{{ route('kpi-umum.index') }}"
                        class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0 me-2">Show</label>
                        <div class="input-group input-group-sm" style="width: 75px;">
                            <select name="per_page" class="form-select"
                                onchange="document.getElementById('filterForm').submit()">
                                @foreach ([10, 25, 50, 75, 100] as $pp)
                                    <option value="{{ $pp }}" {{ (int) $perPage === $pp ? 'selected' : '' }}>
                                        {{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="small text-muted">entries</span>
                    </form>

                    {{-- Search + Add (kanan) --}}
                    <form method="GET" action="{{ route('kpi-umum.index') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <div class="input-group input-group-sm" style="width: 120px;">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control"
                                placeholder="Search...">
                        </div>

                        @if ($me->role === 'hr')
                            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal"
                                data-bs-target="#createModal">
                                <i class="bx bx-plus me-1"></i> Add
                            </button>
                        @endif
                    </form>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive" style="white-space: nowrap; overflow:auto; max-height:65vh;">
                    <table class="table-hover table align-middle">
                        <thead>
                            <tr>
                                <th style="width:60px">#</th>
                                <th>NAMA KPI</th>
                                <th>TIPE</th>
                                <th>SATUAN</th>
                                <th class="text-end">TARGET</th>
                                <th class="text-end">BOBOT (AHP)</th>
                                <th>BULAN</th>
                                <th>TAHUN</th>
                                @if ($me->role === 'hr')
                                    <th style="width:160px">ACTIONS</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($kpis as $i => $k)
                                <tr>
                                    <td>{{ ($kpis->currentPage() - 1) * $kpis->perPage() + $i + 1 }}</td>
                                    <td class="fw-semibold">{{ $k->nama }}</td>
                                    <td class="text-uppercase">{{ $k->tipe }}</td>
                                    <td>{{ $k->satuan ?? '-' }}</td>
                                    <td class="text-end">{{ number_format($k->target, 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        {{ $k->bobot !== null ? rtrim(rtrim(number_format($k->bobot, 8, '.', ''), '0'), '.') : '-' }}
                                    </td>
                                    <td>{{ $bulanList[$k->bulan] ?? $k->bulan }}</td>
                                    <td>{{ $k->tahun }}</td>

                                    @if ($me->role === 'hr')
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                                    data-bs-target="#editModal" data-id="{{ $k->id }}"
                                                    data-nama="{{ $k->nama }}" data-tipe="{{ $k->tipe }}"
                                                    data-satuan="{{ $k->satuan }}" data-target="{{ $k->target }}"
                                                    data-bulan="{{ $k->bulan }}" data-tahun="{{ $k->tahun }}">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>

                                                <form action="{{ route('kpi-umum.destroy', $k) }}" method="POST"
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
                                    <td colspan="{{ $me->role === 'hr' ? 9 : 8 }}" class="text-muted py-4 text-center">
                                        Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

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
                                <li class="page-item disabled"><span class="page-link">«</span></li>
                                <li class="page-item active"><span class="page-link">1</span></li>
                                <li class="page-item disabled"><span class="page-link">»</span></li>
                            </ul>
                        </nav>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Create (2 kolom, tanpa field bobot) --}}
    @if ($me->role === 'hr')
        <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('kpi-umum.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah KPI Umum</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
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
                                <input type="text" name="satuan" class="form-control"
                                    placeholder="cth: Unit / Detik / %">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Target</label>
                                <input type="number" step="0.01" name="target" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Bulan</label>
                                <select name="bulan" class="form-select" required>
                                    @foreach ($bulanList as $num => $label)
                                        <option value="{{ $num }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tahun</label>
                                <input type="number" name="tahun" class="form-control" value="{{ date('Y') }}"
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

        {{-- Modal Edit (2 kolom) --}}
        <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form id="editForm" class="modal-content" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit KPI Umum</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
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

                            <div class="col-md-6">
                                <label class="form-label">Bulan</label>
                                <select id="editBulan" name="bulan" class="form-select" required>
                                    @foreach ($bulanList as $num => $label)
                                        <option value="{{ $num }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
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
            position: 'top-end',
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

        // Konfirmasi hapus
        document.querySelectorAll('form.js-delete').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Hapus KPI ini?',
                    text: 'Tindakan ini tidak bisa dibatalkan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus',
                    cancelButtonText: 'Batal'
                }).then((r) => {
                    if (r.isConfirmed) form.submit();
                });
            });
        });

        // Isi modal edit
        document.getElementById('editModal')?.addEventListener('show.bs.modal', function(event) {
            const b = event.relatedTarget;
            const id = b.getAttribute('data-id');

            document.getElementById('editForm').action = "{{ url('kpi-umum') }}/" + id;
            document.getElementById('editNama').value = b.getAttribute('data-nama') ?? '';
            document.getElementById('editTipe').value = b.getAttribute('data-tipe') ?? 'kuantitatif';
            document.getElementById('editSatuan').value = b.getAttribute('data-satuan') ?? '';
            document.getElementById('editTarget').value = b.getAttribute('data-target') ?? 0;
            document.getElementById('editBulan').value = b.getAttribute('data-bulan') ?? 1;
            document.getElementById('editTahun').value = b.getAttribute('data-tahun') ?? (new Date()).getFullYear();
        });
    </script>
@endpush
