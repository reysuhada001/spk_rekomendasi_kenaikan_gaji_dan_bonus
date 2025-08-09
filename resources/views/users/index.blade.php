@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-2">Management User</h5>

                <div class="d-flex align-items-center justify-content-between gap-2">
                    {{-- Per Page (kiri) --}}
                    <form id="filterForm" method="GET" action="{{ route('users.index') }}"
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
                    <form method="GET" action="{{ route('users.index') }}" class="d-flex align-items-center gap-2">
                        {{-- pertahankan pilihan per_page saat mencari --}}
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
                                <th>NAME</th>
                                <th>NIK</th>
                                <th>PHOTO</th>
                                <th>EMAIL</th>
                                <th>USERNAME</th>
                                <th>PASSWORD</th>
                                <th>PLAIN PASSWORD</th>
                                <th>ROLE</th>
                                <th>DIVISION</th>
                                @if ($me->role === 'hr')
                                    <th style="width:160px">ACTIONS</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $i => $u)
                                <tr>
                                    <td>{{ ($users->currentPage() - 1) * $users->perPage() + $i + 1 }}</td>
                                    <td class="fw-semibold">{{ $u->full_name }}</td>
                                    <td>{{ $u->nik }}</td>
                                    <td>
                                        @if ($u->photo)
                                            <img src="{{ asset('storage/' . $u->photo) }}" alt="photo"
                                                class="rounded-circle" width="36" height="36">
                                        @else
                                            <div class="avatar-initial bg-label-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
                                                style="width:36px;height:36px;">
                                                {{ strtoupper(substr($u->full_name, 0, 1)) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $u->email }}</td>
                                    <td>{{ $u->username }}</td>
                                    <td>••••••••</td>
                                    <td>{{ $u->plain_password }}</td>
                                    <td class="text-uppercase">{{ $u->role }}</td>
                                    <td>{{ $u->division?->name ?? '-' }}</td>

                                    @if ($me->role === 'hr')
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                                    data-bs-target="#editModal" data-id="{{ $u->id }}"
                                                    data-full_name="{{ $u->full_name }}" data-nik="{{ $u->nik }}"
                                                    data-email="{{ $u->email }}" data-username="{{ $u->username }}"
                                                    data-role="{{ $u->role }}"
                                                    data-division_id="{{ $u->division_id ?? '' }}">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>

                                                <form action="{{ route('users.destroy', $u) }}" method="POST"
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
                                    <td colspan="{{ $me->role === 'hr' ? 11 : 10 }}" class="text-muted py-4 text-center">
                                        Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @php
                    $from = $users->count() ? $users->firstItem() : 0;
                    $to = $users->count() ? $users->lastItem() : 0;
                    $total = $users->total();
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $total }}
                        entries</small>

                    @if ($users->hasPages())
                        {{ $users->onEachSide(1)->links() }}
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

    {{-- Modal Create (2 kolom) --}}
    @if ($me->role === 'hr')
        <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('users.store') }}"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NIK</label>
                                <input type="text" name="nik" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="text" name="password" class="form-control" required>
                                <small class="text-muted">* Disimpan juga ke plain_password sesuai permintaan.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <select name="role" id="createRole" class="form-select" required>
                                    <option value="owner">owner</option>
                                    <option value="hr">hr</option>
                                    <option value="leader">leader</option>
                                    <option value="karyawan">karyawan</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Division</label>
                                <select name="division_id" id="createDivision" class="form-select">
                                    <option value="">- Pilih Divisi -</option>
                                    @foreach ($divisions as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Wajib untuk role leader/karyawan.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Foto</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
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

        {{-- Modal Edit (2 kolom, password opsional) --}}
        <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form id="editForm" class="modal-content" method="POST" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama Lengkap</label>
                                <input id="editFullName" type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NIK</label>
                                <input id="editNik" type="text" name="nik" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input id="editEmail" type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input id="editUsername" type="text" name="username" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Password (kosongkan jika tidak ganti)</label>
                                <input type="text" name="password" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Role</label>
                                <select id="editRole" name="role" class="form-select" required>
                                    <option value="owner">owner</option>
                                    <option value="hr">hr</option>
                                    <option value="leader">leader</option>
                                    <option value="karyawan">karyawan</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Division</label>
                                <select id="editDivision" name="division_id" class="form-select">
                                    <option value="">- Pilih Divisi -</option>
                                    @foreach ($divisions as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Wajib untuk role leader/karyawan.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Foto (opsional)</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
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
        // SweetAlert2 Toast
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
                    title: 'Hapus user ini?',
                    text: 'Tindakan ini tidak bisa dibatalkan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus',
                    cancelButtonText: 'Batal'
                }).then((res) => {
                    if (res.isConfirmed) form.submit();
                });
            });
        });

        // ====== Create: toggle division required by role ======
        function toggleCreateDivision() {
            const role = document.getElementById('createRole').value;
            const divSel = document.getElementById('createDivision');
            if (role === 'leader' || role === 'karyawan') {
                divSel.required = true;
                divSel.closest('.col-md-6').style.display = '';
            } else {
                divSel.required = false;
                divSel.value = '';
                divSel.closest('.col-md-6').style.display = '';
            }
        }
        document.getElementById('createRole')?.addEventListener('change', toggleCreateDivision);
        toggleCreateDivision();

        // ====== Edit: fill modal + toggle division by role ======
        document.getElementById('editModal')?.addEventListener('show.bs.modal', function(event) {
            const b = event.relatedTarget;
            const id = b.getAttribute('data-id');

            document.getElementById('editForm').action = "{{ url('users') }}/" + id;
            document.getElementById('editFullName').value = b.getAttribute('data-full_name');
            document.getElementById('editNik').value = b.getAttribute('data-nik');
            document.getElementById('editEmail').value = b.getAttribute('data-email');
            document.getElementById('editUsername').value = b.getAttribute('data-username');
            document.getElementById('editRole').value = b.getAttribute('data-role') ?? 'karyawan';
            document.getElementById('editDivision').value = b.getAttribute('data-division_id') ?? '';

            toggleEditDivision(); // set required/not
        });

        function toggleEditDivision() {
            const role = document.getElementById('editRole').value;
            const divSel = document.getElementById('editDivision');
            if (role === 'leader' || role === 'karyawan') {
                divSel.required = true;
                divSel.closest('.col-md-3').style.display = '';
            } else {
                divSel.required = false;
                divSel.value = '';
                divSel.closest('.col-md-3').style.display = '';
            }
        }
        document.getElementById('editRole')?.addEventListener('change', toggleEditDivision);
    </script>
@endpush
