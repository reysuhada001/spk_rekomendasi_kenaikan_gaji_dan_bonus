<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path=""
    data-template="vertical-menu-template-free">

<head>
    @include('layouts.component.head')
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            @include('layouts.component.sidebar')
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->

                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-xl-0 d-xl-none me-3">
                        <a class="nav-item nav-link me-xl-4 px-0" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Search -->

                        <!-- /Search -->

                        <ul class="navbar-nav align-items-center ms-auto flex-row">


                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="{{ Auth::check() && Auth::user()->photo
                                            ? asset('storage/' . ltrim(Auth::user()->photo, '/'))
                                            : asset('assets/img/user.png') }}"
                                            alt="{{ Auth::check() ? Auth::user()->nama : 'User' }}"
                                            class="rounded-circle"
                                            style="width: 40px; height: 40px; object-fit: cover;" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <!-- Header Profile -->
                                    <li>
                                        <div class="d-flex align-items-center px-3 py-2">
                                            <div class="avatar avatar-online me-2">
                                                <img src="{{ Auth::check() && Auth::user()->photo
                                                    ? asset('storage/' . ltrim(Auth::user()->photo, '/'))
                                                    : asset('assets/img/user.png') }}"
                                                    alt="{{ Auth::check() ? Auth::user()->nama : 'User' }}"
                                                    style="width: 40px; height: 40px; object-fit: cover;"
                                                    class="rounded-circle" />
                                            </div>
                                            <div>
                                                <h6 class="mb-0">{{ Auth::user()->full_name ?? 'User' }}</h6>
                                                <small
                                                    class="text-muted">{{ ucfirst(Auth::user()->role ?? 'Role') }}</small>
                                            </div>
                                        </div>
                                    </li>

                                    <li>
                                        <div class="dropdown-divider my-1"></div>
                                    </li>

                                    <!-- Link ke Profil -->
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-user me-2"></i>
                                            <span>My Profile</span>
                                        </a>
                                    </li>

                                    <li>
                                        <div class="dropdown-divider my-1"></div>
                                    </li>

                                    <!-- Logout -->
                                    <li>
                                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bx bx-log-out me-2"></i>
                                                <span>Logout</span>
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </li>

                            <!--/ User -->
                        </ul>
                    </div>
                </nav>

                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->

                    <div class="container-xxl flex-grow-1 container-p-y">
                        @yield('content')
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div
                            class="container-xxl d-flex justify-content-between flex-md-row flex-column flex-wrap py-2">
                            <div class="mb-md-0 mb-2">
                                ©
                                <script>
                                    document.write(new Date().getFullYear());
                                </script>
                                , <i><b>Universitas Catur Insan Cendekia</b></i>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>

    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <!-- Page JS -->
    <script src="{{ asset('assets/js/dashboards-analytics.js') }}"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    @stack('scripts')
</body>

</html>
