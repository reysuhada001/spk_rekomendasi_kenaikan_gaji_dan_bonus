<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo px-3">
        <a href="#" class="d-flex align-items-center">
            <img src="{{ asset('assets/img/alvarel-mini.png') }}" alt="Logo" style="max-height: 48px;" class="me-2" />
            <span class="fw-bold text-uppercase lh-sm" style="font-size: 11px; line-height: 1.2;">
                PT ALVAREL TECHNOLOGY INNOVATION
            </span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large d-block d-xl-none ms-auto">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>
    <br>
    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        @auth
            @if (auth()->user()->role === 'hr')
                <li class="menu-item {{ request()->routeIs('dashboard.hr') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.hr') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-home-circle"></i>
                        <div data-i18n="Analytics">Dashboard</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Master Data</span>
                </li>

                <li class="menu-item {{ request()->routeIs('divisions.index') ? 'active' : '' }}">
                    <a href="{{ route('divisions.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-building-house"></i>
                        <div data-i18n="Analytics">Division</div>
                    </a>
                </li>

                <li class="menu-item {{ request()->routeIs('users.index') ? 'active' : '' }}">
                    <a href="{{ route('users.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-group"></i>
                        <div data-i18n="Analytics">User</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">KPI Umum</span>
                </li>

                <li class="menu-item {{ request()->routeIs('kpi-umum.index') ? 'active' : '' }}">
                    <a href="{{ route('kpi-umum.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-line-chart"></i>
                        <div data-i18n="Analytics">Data</div>
                    </a>
                </li>
            @endif
        @endauth
    </ul>
</aside>
