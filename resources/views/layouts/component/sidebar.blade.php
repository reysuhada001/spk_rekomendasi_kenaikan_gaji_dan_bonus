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

                <li class="menu-item {{ request()->routeIs('ahp.global.index') ? 'active' : '' }}">
                    <a href="{{ route('ahp.global.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-globe"></i>
                        <div data-i18n="Analytics">Bobot Kriteria</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">KPI Umum</span>
                </li>

                <li class="menu-item {{ request()->routeIs('kpi-umum.index') ? 'active' : '' }}">
                    <a href="{{ route('kpi-umum.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-data"></i>
                        <div data-i18n="Analytics">Data</div>
                    </a>
                </li>

                <li class="menu-item {{ request()->routeIs('ahp.kpi-umum.index') ? 'active' : '' }}">
                    <a href="{{ route('ahp.kpi-umum.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-analyse"></i>
                        <div data-i18n="Analytics">Pembobotan</div>
                    </a>
                </li>

                <li class="menu-item {{ request()->routeIs('realisasi-kpi-umum.index') ? 'active' : '' }}">
                    <a href="{{ route('realisasi-kpi-umum.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-task"></i>
                        <div data-i18n="Analytics">Realisasi</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">KPI Divisi</span>
                </li>

                <li class="menu-item {{ request()->routeIs('kpi-divisi.index') ? 'active' : '' }}">
                    <a href="{{ route('kpi-divisi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-data"></i>
                        <div data-i18n="Analytics">Data</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('ahp.kpi-divisi.index') ? 'active' : '' }}">
                    <a href="{{ route('ahp.kpi-divisi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-analyse"></i>
                        <div data-i18n="Analytics">Pembobotan</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('distribusi-kpi-divisi.index') ? 'active' : '' }}">
                    <a href="{{ route('distribusi-kpi-divisi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-transfer"></i>
                        <div data-i18n="Analytics">Distribusi Target</div>
                    </a>
                </li>

                <li
                    class="menu-item {{ request()->routeIs([
                        'realisasi-kpi-divisi-kuantitatif.index',
                        'realisasi-kpi-divisi-kualitatif.index',
                        'realisasi-kpi-divisi-response.index',
                        'realisasi-kpi-divisi-persentase.index',
                    ])
                        ? 'open'
                        : '' }}">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon tf-icons bx bx-task"></i>
                        <div data-i18n="Analytics">Realisasi Target</div>
                    </a>
                    <ul class="menu-sub">
                        <li
                            class="menu-item {{ request()->routeIs('realisasi-kpi-divisi-kuantitatif.index') ? 'active' : '' }}">
                            <a href="{{ route('realisasi-kpi-divisi-kuantitatif.index') }}" class="menu-link">
                                <div data-i18n="Analytics">Kuantitatif</div>
                            </a>
                        </li>
                        <li
                            class="menu-item {{ request()->routeIs('realisasi-kpi-divisi-kualitatif.index') ? 'active' : '' }}">
                            <a href="{{ route('realisasi-kpi-divisi-kualitatif.index') }}" class="menu-link">
                                <div data-i18n="Analytics">Kualitatif</div>
                            </a>
                        </li>
                        <li
                            class="menu-item {{ request()->routeIs('realisasi-kpi-divisi-response.index') ? 'active' : '' }}">
                            <a href="{{ route('realisasi-kpi-divisi-response.index') }}" class="menu-link">
                                <div data-i18n="Analytics">Response</div>
                            </a>
                        </li>
                        <li
                            class="menu-item {{ request()->routeIs('realisasi-kpi-divisi-persentase.index') ? 'active' : '' }}">
                            <a href="{{ route('realisasi-kpi-divisi-persentase.index') }}" class="menu-link">
                                <div data-i18n="Analytics">Persentase</div>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="menu-item {{ request()->routeIs('kpi-divisi.skor-karyawan.index') ? 'active' : '' }}">
                    <a href="{{ route('kpi-divisi.skor-karyawan.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-medal"></i>
                        <div data-i18n="Analytics">Skor Karyawan</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('kpi-divisi.skor-divisi.index') ? 'active' : '' }}">
                    <a href="{{ route('kpi-divisi.skor-divisi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-bar-chart-alt"></i>
                        <div data-i18n="Analytics">Skor Divisi</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Penilaian Karyawan</span>
                </li>

                <li class="menu-item {{ request()->routeIs('aspek.index') ? 'active' : '' }}">
                    <a href="{{ route('aspek.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-layer"></i>
                        <div data-i18n="Analytics">Aspek</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('peer.admin.index') ? 'active' : '' }}">
                    <a href="{{ route('peer.admin.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Nilai</div>
                    </a>
                </li>
                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Leaderboard</span>
                </li>
                <li class="menu-item {{ request()->routeIs('leaderboard.bulanan.index') ? 'active' : '' }}">
                    <a href="{{ route('leaderboard.bulanan.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Global</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('leaderboard.divisi.index') ? 'active' : '' }}">
                    <a href="{{ route('leaderboard.divisi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Karyawan</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('leaderboard.divisi-kpi.index') ? 'active' : '' }}">
                    <a href="{{ route('leaderboard.divisi-kpi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Divisi</div>
                    </a>
                </li>
            @endif
        @endauth

        @auth
            @if (auth()->user()->role === 'leader')
                <li class="menu-item {{ request()->routeIs('dashboard.leader') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.leader') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-home-circle"></i>
                        <div data-i18n="Analytics">Dashboard</div>
                    </a>
                </li>

                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">KPI Umum</span>
                </li>

                <li class="menu-item {{ request()->routeIs('realisasi-kpi-umum.index') ? 'active' : '' }}">
                    <a href="{{ route('realisasi-kpi-umum.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-task"></i>
                        <div data-i18n="Analytics">Realisasi</div>
                    </a>
                </li>
                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">KPI Divisi</span>
                </li>
                <li class="menu-item {{ request()->routeIs('distribusi-kpi-divisi.index') ? 'active' : '' }}">
                    <a href="{{ route('distribusi-kpi-divisi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-transfer"></i>
                        <div data-i18n="Analytics">Distribusi Target</div>
                    </a>
                </li>

                <li
                    class="menu-item {{ request()->routeIs([
                        'realisasi-kpi-divisi-kuantitatif.index',
                        'realisasi-kpi-divisi-kualitatif.index',
                        'realisasi-kpi-divisi-response.index',
                        'realisasi-kpi-divisi-persentase.index',
                    ])
                        ? 'open'
                        : '' }}">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon tf-icons bx bx-task"></i>
                        <div data-i18n="Analytics">Realisasi Target</div>
                    </a>
                    <ul class="menu-sub">
                        <li
                            class="menu-item {{ request()->routeIs('realisasi-kpi-divisi-kuantitatif.index') ? 'active' : '' }}">
                            <a href="{{ route('realisasi-kpi-divisi-kuantitatif.index') }}" class="menu-link">
                                <div data-i18n="Analytics">Kuantitatif</div>
                            </a>
                        </li>
                        <li
                            class="menu-item {{ request()->routeIs('realisasi-kpi-divisi-kualitatif.index') ? 'active' : '' }}">
                            <a href="{{ route('realisasi-kpi-divisi-kualitatif.index') }}" class="menu-link">
                                <div data-i18n="Analytics">Kualitatif</div>
                            </a>
                        </li>
                        <li
                            class="menu-item {{ request()->routeIs('realisasi-kpi-divisi-response.index') ? 'active' : '' }}">
                            <a href="{{ route('realisasi-kpi-divisi-response.index') }}" class="menu-link">
                                <div data-i18n="Analytics">Response</div>
                            </a>
                        </li>
                        <li
                            class="menu-item {{ request()->routeIs('realisasi-kpi-divisi-persentase.index') ? 'active' : '' }}">
                            <a href="{{ route('realisasi-kpi-divisi-persentase.index') }}" class="menu-link">
                                <div data-i18n="Analytics">Persentase</div>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="menu-item {{ request()->routeIs('kpi-divisi.skor-karyawan.index') ? 'active' : '' }}">
                    <a href="{{ route('kpi-divisi.skor-karyawan.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-medal"></i>
                        <div data-i18n="Analytics">Skor Karyawan</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('kpi-divisi.skor-divisi.index') ? 'active' : '' }}">
                    <a href="{{ route('kpi-divisi.skor-divisi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-bar-chart-alt"></i>
                        <div data-i18n="Analytics">Skor Divisi</div>
                    </a>
                </li>
            @endif
        @endauth

        @auth
            @if (auth()->user()->role === 'karyawan')
                <li class="menu-item {{ request()->routeIs('dashboard.karyawan') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.karyawan') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-home-circle"></i>
                        <div data-i18n="Analytics">Dashboard</div>
                    </a>
                </li>
                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Penilaian Karyawan</span>
                </li>
                <li class="menu-item {{ request()->routeIs('peer.index') ? 'active' : '' }}">
                    <a href="{{ route('peer.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Nilai</div>
                    </a>
                </li>
                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Rekomendasi</span>
                </li>
                <li class="menu-item {{ request()->routeIs('bonus.rekomendasi.index') ? 'active' : '' }}">
                    <a href="{{ route('bonus.rekomendasi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Bonus</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('salary.raise.index') ? 'active' : '' }}">
                    <a href="{{ route('salary.raise.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Kenaikan Gaji</div>
                    </a>
                </li>
                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text">Leaderboard</span>
                </li>
                <li class="menu-item {{ request()->routeIs('leaderboard.bulanan.index') ? 'active' : '' }}">
                    <a href="{{ route('leaderboard.bulanan.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Global</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('leaderboard.divisi.index') ? 'active' : '' }}">
                    <a href="{{ route('leaderboard.divisi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Karyawan</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('leaderboard.divisi-kpi.index') ? 'active' : '' }}">
                    <a href="{{ route('leaderboard.divisi-kpi.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-check-circle"></i>
                        <div data-i18n="Analytics">Divisi</div>
                    </a>
                </li>
            @endif
        @endauth
    </ul>
</aside>
