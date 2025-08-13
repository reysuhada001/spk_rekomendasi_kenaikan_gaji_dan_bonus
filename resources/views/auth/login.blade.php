<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Boxicons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background: #f5f6fa;
        }

        .login-card {
            max-width: 420px;
            width: 100%;
        }

        .brand-badge {
            height: 42px;
            width: 42px;
            display: inline-grid;
            place-items: center;
            border-radius: 12px;
            background: #696cff;
            color: #fff;
            font-size: 22px;
            font-weight: 700;
        }

        .form-control:focus,
        .input-group-text:focus {
            border-color: #696cff;
            box-shadow: 0 0 0 .2rem rgba(105, 108, 255, .25);
        }

        .dot-col {
            position: absolute;
            right: 3%;
            top: 10%;
            opacity: .5;
            pointer-events: none;
        }

        .dot {
            height: 6px;
            width: 6px;
            border-radius: 50%;
            background: #696cff;
            margin: 10px;
        }
    </style>
</head>

<body>
    <div class="d-flex align-items-center justify-content-center container" style="min-height:100vh; position:relative;">
        <!-- Dots dekoratif (opsional) -->
        <div class="dot-col d-none d-lg-block">
            <div class="d-flex flex-column align-items-center">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
        </div>

        <div class="card login-card border-0 shadow-sm">
            <div class="card-body p-sm-5 p-4">

                <div class="mb-4 text-center">
                    <img src="{{ asset('assets/img/alvarel-mini.png') }}" alt="Logo"
                        style="height: 80px; width: 80px; border-radius: 16px;">
                    <div class="fw-bold mt-2" style="font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                        PT ALVAREL TECHNOLOGY INNOVATION
                    </div>
                </div>

                <!-- Form -->
                <form method="POST" action="{{ route('login.post') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email or Username</label>
                        <input type="text" name="login" class="form-control form-control-lg"
                            placeholder="Enter your email or username" value="{{ old('login') }}" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Password</label>
                        <div class="input-group input-group-lg">
                            <input type="password" name="password" id="password" class="form-control"
                                placeholder="••••••••" required>
                            <button class="input-group-text" type="button" id="togglePass"
                                aria-label="Toggle password">
                                <i class="bx bx-hide" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notif -->
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

        // Show/Hide password
        const toggle = document.getElementById('togglePass');
        const pwd = document.getElementById('password');
        const eye = document.getElementById('eyeIcon');
        toggle?.addEventListener('click', () => {
            const isText = pwd.type === 'text';
            pwd.type = isText ? 'password' : 'text';
            eye.classList.toggle('bx-hide', !isText);
            eye.classList.toggle('bx-show', isText);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
