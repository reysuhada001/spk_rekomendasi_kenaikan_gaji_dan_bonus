@extends('layouts.app')

@push('styles')
    <style>
        th.password-col {
            width: 280px;
            min-width: 260px;
        }

        td .password-cell {
            display: flex;
            align-items: center;
            gap: .5rem;
            width: 100%;
            min-width: 260px;
            max-width: 360px;
            white-space: nowrap;
        }

        /* HILANGKAN BORDER INPUT di kolom password */
        .password-cell input,
        .password-cell input:focus,
        .password-cell input[readonly] {
            flex: 1;
            min-width: 0;
            background: transparent !important;
            border: 0 !important;
            border-color: transparent !important;
            border-width: 0 !important;
            outline: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            font-family: inherit;
            font-size: 1rem;
            -webkit-appearance: none;
            appearance: none;
        }

        .password-cell .btn {
            flex-shrink: 0;
        }
    </style>
@endpush


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
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <div class="input-group input-group-sm" style="width: 200px;">
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
                                <th>PHOTO</th>
                                <th>NIK</th>
                                <th>Nama Lengkap</th>
                                <th>EMAIL</th>
                                <th>USERNAME</th>
                                <th class="password-col">PASSWORD</th>
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

                                    {{-- PHOTO --}}
                                    <td>
                                        @if ($u->photo)
                                            <img src="{{ asset('storage/' . ltrim($u->photo, '/')) }}" alt="photo"
                                                class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
                                        @else
                                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-light text-secondary"
                                                style="width:36px;height:36px;">
                                                {{ strtoupper(substr($u->full_name, 0, 1)) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $u->nik }}</td>
                                    <td>{{ $u->full_name }}</td>
                                    <td class="fw-semibold">{{ $u->email }}</td>
                                    <td>{{ $u->username }}</td>

                                    {{-- PASSWORD (isi plain_password, toggle show/hide) --}}
                                    <td>
                                        <div class="password-cell">
                                            <input type="password" value="{{ $u->plain_password }}" readonly
                                                id="pp-input-{{ $u->id }}"
                                                style="border:0!important;box-shadow:none!important;background:transparent!important;outline:none!important;padding:0!important;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                data-toggle-plain="#pp-input-{{ $u->id }}"
                                                aria-label="Toggle password" title="Show/Hide">
                                                <i class="bx bx-hide"></i>
                                            </button>
                                        </div>
                                    </td>

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
                                    <td colspan="{{ $me->role === 'hr' ? 8 : 7 }}" class="text-muted py-4 text-center">
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

                    // Hitung range halaman (mimic onEachSide(1)) + ellipsis
                    $current = $users->currentPage();
                    $last = $users->lastPage();

                    $start = max(1, $current - 1);
                    $end = min($last, $current + 1);
                @endphp
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $from }} to {{ $to }} of {{ $total }}
                        entries</small>

                    {{-- PAGINATION GAYA SNEAT (selalu tampil, meski 1 halaman) --}}
                    <nav>
                        <ul class="pagination justify-content-end mb-0">
                            {{-- Prev --}}
                            @if ($current <= 1)
                                <li class="page-item disabled"><span class="page-link">«</span></li>
                            @else
                                <li class="page-item">
                                    <a class="page-link" href="{{ $users->previousPageUrl() }}" rel="prev">«</a>
                                </li>
                            @endif

                            {{-- First page --}}
                            @if ($start > 1)
                                <li class="page-item {{ $current === 1 ? 'active' : '' }}">
                                    @if ($current === 1)
                                        <span class="page-link">1</span>
                                    @else
                                        <a class="page-link" href="{{ $users->url(1) }}">1</a>
                                    @endif
                                </li>
                                @if ($start > 2)
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                @endif
                            @endif

                            {{-- Middle range (current ±1) --}}
                            @for ($p = $start; $p <= $end; $p++)
                                <li class="page-item {{ $p === $current ? 'active' : '' }}">
                                    @if ($p === $current)
                                        <span class="page-link">{{ $p }}</span>
                                    @else
                                        <a class="page-link" href="{{ $users->url($p) }}">{{ $p }}</a>
                                    @endif
                                </li>
                            @endfor

                            {{-- Last page --}}
                            @if ($end < $last)
                                @if ($end < $last - 1)
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                @endif
                                <li class="page-item {{ $current === $last ? 'active' : '' }}">
                                    @if ($current === $last)
                                        <span class="page-link">{{ $last }}</span>
                                    @else
                                        <a class="page-link" href="{{ $users->url($last) }}">{{ $last }}</a>
                                    @endif
                                </li>
                            @endif

                            {{-- Next --}}
                            @if ($current >= $last)
                                <li class="page-item disabled"><span class="page-link">»</span></li>
                            @else
                                <li class="page-item">
                                    <a class="page-link" href="{{ $users->nextPageUrl() }}" rel="next">»</a>
                                </li>
                            @endif
                        </ul>
                    </nav>
                    {{-- END PAGINATION GAYA SNEAT --}}
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Create --}}
    @if ($me->role === 'hr')
        <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
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
                                <div class="input-group">
                                    <input type="password" name="password" id="createPassword" class="form-control"
                                        required>
                                    <button class="input-group-text" type="button" id="toggleCreatePassword"
                                        aria-label="Toggle password">
                                        <i class="bx bx-hide"></i>
                                    </button>
                                </div>
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
                                <small class="text-muted d-block">Disarankan rasio persegi. Maks: 1MB.</small>
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
            <div class="modal-dialog modal-dialog-centered modal-lg">
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
                                <div class="input-group">
                                    <input type="password" name="password" id="editPassword" class="form-control">
                                    <button class="input-group-text" type="button" id="toggleEditPassword"
                                        aria-label="Toggle password">
                                        <i class="bx bx-hide"></i>
                                    </button>
                                </div>
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
        // Toast
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
            form.addEventListener('submit', e => {
                e.preventDefault();
                Swal.fire({
                    title: 'Hapus user ini?',
                    text: 'Tindakan ini tidak bisa dibatalkan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus',
                    cancelButtonText: 'Batal'
                }).then(res => {
                    if (res.isConfirmed) form.submit();
                });
            });
        });

        // Toggle wajib division (create)
        function toggleCreateDivision() {
            const role = document.getElementById('createRole')?.value;
            const divSel = document.getElementById('createDivision');
            if (!divSel) return;
            if (role === 'leader' || role === 'karyawan') {
                divSel.required = true;
            } else {
                divSel.required = false;
                divSel.value = '';
            }
        }
        document.getElementById('createRole')?.addEventListener('change', toggleCreateDivision);
        toggleCreateDivision();

        // Set data edit + toggle wajib division
        document.getElementById('editModal')?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('editForm').action = "{{ url('users') }}/" + b.getAttribute('data-id');
            document.getElementById('editFullName').value = b.getAttribute('data-full_name');
            document.getElementById('editNik').value = b.getAttribute('data-nik');
            document.getElementById('editEmail').value = b.getAttribute('data-email');
            document.getElementById('editUsername').value = b.getAttribute('data-username');
            document.getElementById('editRole').value = b.getAttribute('data-role') ?? 'karyawan';
            document.getElementById('editDivision').value = b.getAttribute('data-division_id') ?? '';
            toggleEditDivision();
        });

        function toggleEditDivision() {
            const role = document.getElementById('editRole')?.value;
            const divSel = document.getElementById('editDivision');
            if (!divSel) return;
            if (role === 'leader' || role === 'karyawan') {
                divSel.required = true;
            } else {
                divSel.required = false;
                divSel.value = '';
            }
        }
        document.getElementById('editRole')?.addEventListener('change', toggleEditDivision);

        // Toggle password (create/edit)
        function wireToggle(btnId, inputId) {
            const btn = document.getElementById(btnId),
                input = document.getElementById(inputId);
            btn?.addEventListener('click', () => {
                const isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                const icon = btn.querySelector('i');
                icon?.classList.toggle('bx-hide', !isText);
                icon?.classList.toggle('bx-show', isText);
            });
        }
        wireToggle('toggleCreatePassword', 'createPassword');
        wireToggle('toggleEditPassword', 'editPassword');

        // Toggle plain password di tabel
        document.querySelectorAll('[data-toggle-plain]').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.querySelector(btn.getAttribute('data-toggle-plain'));
                if (!input) return;
                const isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                const icon = btn.querySelector('i');
                icon?.classList.toggle('bx-hide', !isText);
                icon?.classList.toggle('bx-show', isText);
            });
        });
    </script>
@endpush
