    <div class="container" style="max-width:420px;">
        <div class="card mt-5 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Login</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('login.post') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email atau Username</label>
                        <input type="text" name="login" class="form-control" value="{{ old('login') }}" required
                            autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Masuk</button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
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
        </script>
    @endpush
