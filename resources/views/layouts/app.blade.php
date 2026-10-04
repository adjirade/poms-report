<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title')@else{{ $title ?? 'POMS Report' }}@endif - Palm Oil Mill System</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%2315803d'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle'%3E%F0%9F%8C%BF%3C/text%3E%3C/svg%3E">
    
    <!-- Aset lokal (Vite build): Tailwind + Alpine + Chart.js + Font Awesome -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles
    
    <style>
        [x-cloak] { display: none !important; }

        /* Sidebar mode ikon (rail) saat collapsed di desktop */
        @media (min-width: 768px) {
            .sidebar-collapsed nav a,
            .sidebar-collapsed nav button { justify-content: center; }
            .sidebar-collapsed nav a > *:not([hidden]),
            .sidebar-collapsed nav button > *:not([hidden]) { margin-left: 0 !important; margin-right: 0 !important; }
        }
        
        /* Print styles */
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="text-gray-800"
      x-data="{
          mobileOpen: false,
          collapsed: localStorage.getItem('poms.sidebar.collapsed') === 'true',
          userMenu: false,
          toggle() {
              if (window.innerWidth < 768) {
                  this.mobileOpen = !this.mobileOpen;
              } else {
                  this.collapsed = !this.collapsed;
                  localStorage.setItem('poms.sidebar.collapsed', this.collapsed);
              }
          }
      }">

    <!-- Lewati ke konten (aksesibilitas keyboard) -->
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-green-700 focus:shadow-lg">
        Lompat ke konten utama
    </a>

    <!-- Backdrop mobile -->
    <div x-show="mobileOpen" x-cloak @click="mobileOpen = false"
         class="fixed inset-0 bg-black/50 z-30 md:hidden"></div>
    
    <!-- Sidebar -->
    <aside :class="[ collapsed ? 'md:w-20 sidebar-collapsed' : 'md:w-64', mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0' ]"
           class="sidebar sidebar-glass fixed left-0 top-0 z-40 flex h-full w-64 flex-col text-white transition-all duration-300 no-print">
        
        <!-- Logo & Toggle -->
        <div class="flex items-center justify-between border-b border-white/15 p-4">
            <div class="flex items-center space-x-2">
                <div class="glass-sheen relative flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-white/25 bg-white/15 text-white shadow-float">
                    <i class="fas fa-leaf text-lg"></i>
                </div>
                <div x-show="mobileOpen || !collapsed" x-cloak>
                    <p class="font-bold leading-tight">POMS Report</p>
                    <p class="text-[11px] leading-tight text-emerald-300/90">{{ auth()->user()->plant_id }}</p>
                </div>
            </div>
            <button @click="collapsed = true; localStorage.setItem('poms.sidebar.collapsed', true)"
                    class="hidden rounded-xl p-1.5 transition hover:bg-white/10 md:flex" aria-label="Perkecil menu">
                <i class="fas fa-chevron-left text-xs"></i>
            </button>
        </div>
        
        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            @php
                $activeNav = 'bg-white/[0.18] font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)]';
                $idleNav = 'text-emerald-50/85 hover:bg-white/10 hover:text-white';
            @endphp

            <!-- === MENU UTAMA === -->
            <p x-show="mobileOpen || !collapsed" x-cloak class="px-3 pb-1 pt-1 text-[10px] font-bold uppercase tracking-widest text-emerald-300/80">Menu</p>

            {{-- Input data (operator & role lain) --}}
            @can('submit-data')
            <a href="{{ route('input.index') }}"
               class="flex items-center space-x-3 p-2.5 rounded-lg transition {{ request()->routeIs('input.*') ? $activeNav : $idleNav }}">
                <i class="fas fa-pen-to-square w-5 text-center"></i>
                <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Input Data</span>
            </a>
            @endcan

            {{-- Kalkulator operasional (semua user web) --}}
            <a href="{{ route('tools.kalkulator') }}"
               class="flex items-center space-x-3 p-2.5 rounded-lg transition {{ request()->routeIs('tools.*') ? $activeNav : $idleNav }}">
                <i class="fas fa-calculator w-5 text-center"></i>
                <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Kalkulator</span>
            </a>

            {{-- Dashboard (butuh akses web; operator tidak) --}}
            @can('access-web')
            <a href="{{ route('dashboard') }}"
               class="flex items-center space-x-3 p-2.5 rounded-lg transition {{ request()->routeIs('dashboard') ? $activeNav : $idleNav }}">
                <i class="fas fa-home w-5 text-center"></i>
                <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Dashboard</span>
            </a>
            @endcan
            
            <!-- Stations -->
            @php
                $allowedStations = [
                    'timbang' => 'Timbang',
                    'sortasi' => 'Sortasi',
                    'sterilizer' => 'Sterilizer',
                    'press' => 'Press',
                    'klarifikasi' => 'Klarifikasi',
                    'kernel' => 'Kernel',
                    'lab' => 'Lab',
                    'maintenance' => 'Maintenance',
                ];
                $userStations = auth()->user()->allowedStations();
                $visibleStations = is_array($userStations)
                    ? array_intersect_key($allowedStations, array_flip($userStations))
                    : $allowedStations;
            @endphp
            @if(count($visibleStations) > 0)
            <div x-data="{ open: {{ request()->routeIs('stations.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" class="w-full flex items-center justify-between p-2.5 rounded-lg {{ $idleNav }} {{ request()->routeIs('stations.*') ? $activeNav : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-industry w-5 text-center"></i>
                        <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Stasiun</span>
                    </div>
                    <i x-show="mobileOpen || !collapsed" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[10px]"></i>
                </button>
                <div x-show="open && (mobileOpen || !collapsed)" x-cloak class="ml-5 mt-1 space-y-0.5 border-l border-white/20 pl-2">
                    @foreach($visibleStations as $stationKey => $stationLabel)
                    <a href="{{ route('stations.' . $stationKey) }}"
                       class="block rounded-lg p-2 text-sm {{ request()->routeIs('stations.' . $stationKey) ? 'bg-white/[0.16] font-semibold text-white' : 'text-emerald-100/80 hover:bg-white/10' }}">
                        {{ $stationLabel }}
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
            
            <!-- Flagged Records -->
            @can('view-flagged-records')
            <a href="{{ route('flagged.records') }}" 
               class="flex items-center space-x-3 p-2.5 rounded-lg transition {{ request()->routeIs('flagged.records') ? $activeNav : $idleNav }}">
                <i class="fas fa-flag w-5 text-center"></i>
                <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Data Flagged</span>
            </a>
            @endcan
            
            <!-- === ANALISIS === -->
            @can('access-full-dashboard')
            <p x-show="mobileOpen || !collapsed" x-cloak class="px-3 pb-1 pt-3 text-[10px] font-bold uppercase tracking-widest text-emerald-300/80">Analisis</p>

            <div x-data="{ open: {{ request()->routeIs('analytics.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" class="w-full flex items-center justify-between p-2.5 rounded-lg {{ $idleNav }} {{ request()->routeIs('analytics.*') ? $activeNav : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chart-line w-5 text-center"></i>
                        <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Analytics</span>
                    </div>
                    <i x-show="mobileOpen || !collapsed" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[10px]"></i>
                </button>
                <div x-show="open && (mobileOpen || !collapsed)" x-cloak class="ml-5 mt-1 space-y-0.5 border-l border-white/20 pl-2">
                    <a href="{{ route('analytics.command-center') }}" class="block rounded-lg p-2 text-sm {{ request()->routeIs('analytics.command-center') ? 'bg-white/[0.16] font-semibold text-white' : 'text-emerald-100/80 hover:bg-white/10' }}">Command Center</a>
                    <a href="{{ route('analytics.overview') }}" class="block rounded-lg p-2 text-sm {{ request()->routeIs('analytics.overview') ? 'bg-white/[0.16] font-semibold text-white' : 'text-emerald-100/80 hover:bg-white/10' }}">Overview</a>
                    <a href="{{ route('analytics.losses') }}" class="block rounded-lg p-2 text-sm {{ request()->routeIs('analytics.losses') ? 'bg-white/[0.16] font-semibold text-white' : 'text-emerald-100/80 hover:bg-white/10' }}">Losses</a>
                    <a href="{{ route('analytics.efficiency') }}" class="block rounded-lg p-2 text-sm {{ request()->routeIs('analytics.efficiency') ? 'bg-white/[0.16] font-semibold text-white' : 'text-emerald-100/80 hover:bg-white/10' }}">Efficiency</a>
                    @can('view-department-data')
                    <a href="{{ route('analytics.station-performance') }}" class="block rounded-lg p-2 text-sm {{ request()->routeIs('analytics.station-performance') ? 'bg-white/[0.16] font-semibold text-white' : 'text-emerald-100/80 hover:bg-white/10' }}">Performa Stasiun</a>
                    @endcan
                </div>
            </div>
            @endcan
            
            <!-- HQ Multi-plant -->
            @can('view-all-plants')
            <div x-data="{ open: {{ request()->routeIs('hq.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" class="w-full flex items-center justify-between p-2.5 rounded-lg {{ $idleNav }} {{ request()->routeIs('hq.*') ? $activeNav : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-building w-5 text-center"></i>
                        <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">HQ</span>
                    </div>
                    <i x-show="mobileOpen || !collapsed" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[10px]"></i>
                </button>
                <div x-show="open && (mobileOpen || !collapsed)" x-cloak class="ml-5 mt-1 space-y-0.5 border-l border-white/20 pl-2">
                    <a href="{{ route('hq.dashboard') }}" class="block rounded-lg p-2 text-sm {{ request()->routeIs('hq.dashboard') ? 'bg-white/[0.16] font-semibold text-white' : 'text-emerald-100/80 hover:bg-white/10' }}">Dashboard</a>
                    <a href="{{ route('hq.comparison') }}" class="block rounded-lg p-2 text-sm {{ request()->routeIs('hq.comparison') ? 'bg-white/[0.16] font-semibold text-white' : 'text-emerald-100/80 hover:bg-white/10' }}">Perbandingan Plant</a>
                </div>
            </div>
            @endcan
            
            <!-- === SISTEM === -->
            @if(auth()->user()->can('edit-validation-rules') || auth()->user()->can('access-settings') || auth()->user()->can('manage-users'))
            <p x-show="mobileOpen || !collapsed" x-cloak class="px-3 pb-1 pt-3 text-[10px] font-bold uppercase tracking-widest text-emerald-300/80">Sistem</p>
            @endif

            @can('edit-validation-rules')
            <a href="{{ route('settings.validation-rules') }}" 
               class="flex items-center space-x-3 p-2.5 rounded-lg transition {{ request()->routeIs('settings.validation-rules*') ? $activeNav : $idleNav }}">
                <i class="fas fa-cog w-5 text-center"></i>
                <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Aturan Validasi</span>
            </a>
            @endcan
            
            <!-- HQ Sync Monitor (developer) -->
            @can('access-settings')
            <a href="{{ route('settings.hq-sync') }}" 
               class="flex items-center space-x-3 p-2.5 rounded-lg transition {{ request()->routeIs('settings.hq-sync*') ? $activeNav : $idleNav }}">
                <i class="fas fa-satellite-dish w-5 text-center"></i>
                <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">HQ Sync Monitor</span>
            </a>
            <a href="{{ route('settings.environment') }}"
               class="flex items-center space-x-3 p-2.5 rounded-lg transition {{ request()->routeIs('settings.environment*') ? $activeNav : $idleNav }}">
                <i class="fas fa-sliders w-5 text-center"></i>
                <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Environment &amp; Integrasi</span>
            </a>
            @endcan

            <!-- Manajemen User (developer) -->
            @can('manage-users')
            <a href="{{ route('settings.users') }}"
               class="flex items-center space-x-3 p-2.5 rounded-lg transition {{ request()->routeIs('settings.users*') ? $activeNav : $idleNav }}">
                <i class="fas fa-users-cog w-5 text-center"></i>
                <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Manajemen User</span>
            </a>
            @endcan
        </nav>
        
        <!-- User + Logout -->
        <div class="space-y-1 border-t border-white/15 p-3">
            <a href="{{ route('profile') }}"
               class="flex items-center space-x-3 rounded-xl p-2 hover:bg-white/10 {{ request()->routeIs('profile') ? 'bg-white/15 font-semibold' : '' }}">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15 text-xs font-bold">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div x-show="mobileOpen || !collapsed" x-cloak class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-emerald-300/90">{{ ucfirst(auth()->user()->role) }}</p>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center space-x-3 p-2 rounded-lg hover:bg-red-600/80 text-left transition">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i>
                    <span x-show="mobileOpen || !collapsed" x-cloak class="text-sm">Keluar</span>
                </button>
            </form>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div :class="collapsed ? 'md:ml-20' : 'md:ml-64'" class="main-content min-h-screen overflow-x-hidden transition-all duration-300">
        
        <!-- Header -->
        <header class="glass-bar sticky top-0 z-20 border-x-0 border-t-0 border-b border-white/50 px-4 py-3 no-print">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <button @click="toggle()"
                            class="rounded-xl p-2 text-gray-600 transition hover:bg-white/60"
                            :aria-label="mobileOpen ? 'Tutup menu' : 'Buka menu'" :aria-expanded="mobileOpen">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-bold text-gray-800 sm:text-xl">@hasSection('title')@yield('title')@else{{ $title ?? 'Dashboard' }}@endif</h1>
                        <p class="hidden truncate text-xs text-gray-500 sm:block">@hasSection('subtitle')@yield('subtitle')@else{{ $subtitle ?? 'Sistem Pelaporan Digital Pabrik Kelapa Sawit' }}@endif</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-xs text-gray-500 hidden lg:flex items-center gap-1.5">
                        <i class="fas fa-map-marker-alt text-green-600"></i>{{ auth()->user()->plant_id }}
                        <span class="text-gray-300 mx-1">|</span>
                        <i class="far fa-clock"></i><span x-data="{ now: new Date() }" x-init="setInterval(() => { now = new Date() }, 30000)"
                              x-text="new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(now)">{{ now()->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span> WIB
                    </span>
                    <!-- User dropdown -->
                    <div class="relative" @click.outside="userMenu = false">
                        <button @click="userMenu = !userMenu"
                                class="flex items-center space-x-2 rounded-full p-1.5 pr-2.5 transition hover:bg-white/60">
                            <div class="w-8 h-8 bg-green-700 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                            <span class="text-sm font-medium text-gray-700 hidden sm:block">{{ auth()->user()->name }}</span>
                            <i class="fas fa-chevron-down text-[10px] text-gray-400"></i>
                        </button>
                        <div x-show="userMenu" x-cloak x-transition
                             class="glass absolute right-0 z-30 mt-2 w-56 overflow-hidden py-1.5 shadow-glass-lg">
                            <div class="border-b border-white/50 px-4 py-2">
                                <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-gray-500">{{ auth()->user()->phone_number }}</p>
                            </div>
                            <a href="{{ route('profile') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 transition hover:bg-white/60">
                                <i class="fas fa-user-circle w-4 text-gray-400"></i> Profil Saya
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-red-600 transition hover:bg-red-500/10">
                                    <i class="fas fa-sign-out-alt w-4"></i> Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Flash Messages -->
        @if(session('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show" x-transition role="status" class="no-print mx-4 mt-4 flex items-start justify-between rounded-2xl border border-green-300/60 bg-green-100/70 p-4 text-green-900 shadow-glass backdrop-blur-xl">
            <p><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</p>
            <button @click="show = false" class="text-green-600 hover:text-green-800"><i class="fas fa-times"></i></button>
        </div>
        @endif
        
        @if(session('error'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 10000)" x-show="show" x-transition role="alert" class="no-print mx-4 mt-4 flex items-start justify-between rounded-2xl border border-red-300/60 bg-red-100/70 p-4 text-red-900 shadow-glass backdrop-blur-xl">
            <p><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</p>
            <button @click="show = false" class="text-red-600 hover:text-red-800"><i class="fas fa-times"></i></button>
        </div>
        @endif
        
        @if(session('warning'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show" x-transition role="status" class="no-print mx-4 mt-4 flex items-start justify-between rounded-2xl border border-yellow-300/60 bg-yellow-100/70 p-4 text-yellow-900 shadow-glass backdrop-blur-xl">
            <p><i class="fas fa-exclamation-triangle mr-2"></i>{{ session('warning') }}</p>
            <button @click="show = false" class="text-yellow-600 hover:text-yellow-800"><i class="fas fa-times"></i></button>
        </div>
        @endif
        
        <!-- Page Content -->
        <main id="main-content" class="p-4 md:p-6">
            @yield('content')
        </main>
        
        <!-- Footer -->
        <footer class="glass-bar border-x-0 border-b-0 px-4 py-3 text-center text-xs text-gray-500 no-print">
            <p>&copy; {{ date('Y') }} POMS Report System · {{ config('poms.plant_name') }} · v1.0</p>
        </footer>
    </div>
    
    @livewireScripts
    
    @stack('scripts')
    
</body>
</html>
