@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            {{-- Header --}}
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Management Aspek</h5>

                <div class="d-flex align-items-center ms-auto flex-wrap gap-2">
                    {{-- Per page --}}
                    <form id="filterForm" method="GET" action="{{ route('aspek.index') }}"
                        class="d-flex align-items-center gap-2">
                        <span class="small text-muted">Show</span>
                        <div class="input-group input-group-sm" style="width:110px;">
                            <select name="per_page" class="form-select"
                                onchange="document.getElementById('filterForm').submit()">
                                @foreach ([10, 25, 50, 75, 100] as $pp)
                                    <option value="{{ $pp }}" {{ (int) $perPage === $pp ? 'selected' : '' }}>
                                        {{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="small text-muted">entries</span>
                        <input type="hidden" name="search" value="{{ $search }}">
                    </form>

                    {{-- Search --}}
                    <form method="GET" action="{{ route('aspek.index') }}" class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="width:240px;">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Cari nama/tahun..."
                                value="{{ $search }}">
                        </div>
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <button class="btn btn-secondary btn-sm" type="submit">Cari</button>
                    </form>

                    {{-- Add (HR only) --}}
                    @if ($me->role === 'hr')
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
                            <i class="bx bx-plus me-1"></i> Add
                        </button>
                    @endif
                </div>
            </div>

            {{-- Body --}}
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
                                <th>NAMA ASPEK</th>
                                <th>BULAN</th>
                                <th>TAHUN</th>
                                @if ($me->role === 'hr')
                                    <th style="width:160px">ACTIONS</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($aspeks as $i => $a)
                                <tr>
                                    <td>{{ ($aspeks->currentPage() - 1) * $aspeks->perPage() + $i + 1 }}</td>
                                    <td class="fw-semibold">{{ $a->nama }}</td>
                                    <td>{{ $bulanList[$a->bulan] ?? $a->bulan }}</td>
                                    <td>{{ $a->tahun }}</td>
                                    @if ($me->role === 'hr')
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                                    data-bs-target="#editModal" data-id="{{ $a->id }}"
                                                    data-nama="{{ $a->nama }}" data-bulan="{{ $a->bulan }}"
                                                    data-tahun="{{ $a->tahun }}">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <form action="{{ route('aspek.destroy', $a) }}" method="POST"
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
                                    <td colspan="{{ $me->role === 'hr' ? 5 : 4 }}" class="text-muted py-4 text-center">
                                        Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Info + pagination --}}
                @php
                    $from = $aspeks->count() ? $aspeks->firstItem() : 0;
                    $to = $aspeks->count() ? $aspeks->lastItem() : 0;
                    $total = $aspeks->total();
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $total }}
                        entries</small>
                    @if ($aspeks->hasPages())
                        {{ $aspeks->onEachSide(1)->links() }}
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
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('aspek.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Aspek</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nama Aspek</label>
                                <input type="text" name="nama" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bulan</label>
                                <select name="bulan" class="form-select" required>
                                    @foreach ($bulanList as $num => $lbl)
                                        <option value="{{ $num }}">{{ $lbl }}</option>
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

        {{-- Modal Edit --}}
        <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form id="editForm" class="modal-content" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Aspek</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nama Aspek</label>
                                <input id="editNama" type="text" name="nama" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bulan</label>
                                <select id="editBulan" name="bulan" class="form-select" required>
                                    @foreach ($bulanList as $num => $lbl)
                                        <option value="{{ $num }}">{{ $lbl }}</option>
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

        // Konfirmasi hapus
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

        // Isi modal edit
        document.getElementById('editModal')?.addEventListener('show.bs.modal', function(ev) {
            const b = ev.relatedTarget;
            const id = b.getAttribute('data-id');
            document.getElementById('editForm').action = "{{ url('aspek') }}/" + id;
            document.getElementById('editNama').value = b.getAttribute('data-nama') ?? '';
            document.getElementById('editBulan').value = b.getAttribute('data-bulan') ?? 1;
            document.getElementById('editTahun').value = b.getAttribute('data-tahun') ?? (new Date()).getFullYear();
        });
    </script>
@endpush
