@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">Data Divisi</h5>

                {{-- Toolbar: per page kiri, search kanan --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    {{-- Per Page --}}
                    <form id="filterForm" method="GET" action="{{ route('divisions.index') }}"
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

                    {{-- Search --}}
                    <form method="GET" action="{{ route('divisions.index') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <div class="input-group input-group-sm" style="width: 120px;">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control"
                                placeholder="Search...">
                        </div>
                        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal"
                            data-bs-target="#createModal">
                            <i class="bx bx-plus me-1"></i> Add
                        </button>
                    </form>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive" style="white-space: nowrap; overflow:auto; max-height:65vh;">
                    <table class="table-hover table align-middle">
                        <thead>
                            <tr>
                                <th style="width:60px">#</th>
                                <th>NAME</th>
                                <th style="width:160px">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($divisions as $i => $division)
                                <tr>
                                    <td>{{ ($divisions->currentPage() - 1) * $divisions->perPage() + $i + 1 }}</td>
                                    <td class="fw-semibold">{{ $division->name }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                                data-bs-target="#editModal" data-id="{{ $division->id }}"
                                                data-name="{{ $division->name }}">
                                                <i class="bx bx-edit-alt"></i>
                                            </button>

                                            <form action="{{ route('divisions.destroy', $division) }}" method="POST"
                                                class="js-delete d-inline">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted py-4 text-center">Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @php
                    $from = $divisions->count() ? $divisions->firstItem() : 0;
                    $to = $divisions->count() ? $divisions->lastItem() : 0;
                    $total = $divisions->total();
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $total }}
                        entries</small>

                    @if ($divisions->hasPages())
                        {{ $divisions->onEachSide(1)->links() }}
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

    {{-- Modal Create --}}
    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('divisions.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Divisi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Divisi</label>
                        <input type="text" name="name" class="form-control" placeholder="cth: Human Resources"
                            required>
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
        <div class="modal-dialog modal-dialog-centered">
            <form id="editForm" class="modal-content" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Divisi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Divisi</label>
                        <input id="editName" type="text" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
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

        // Konfirmasi hapus
        document.querySelectorAll('form.js-delete').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Hapus data ini?',
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
            const btn = event.relatedTarget;
            const id = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            const form = document.getElementById('editForm');
            form.action = "{{ url('divisions') }}/" + id;
            document.getElementById('editName').value = name;
        });
    </script>
@endpush
