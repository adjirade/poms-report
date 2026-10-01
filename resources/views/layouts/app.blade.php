<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'POMS Report' }} - Palm Oil Mill System</title>
    
    <!-- Aset lokal (Vite build): Tailwind + Alpine + Chart.js + Font Awesome -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles
    
    <style>
        [x-cloak] { display: none !important; }
        
        /* Print styles */
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="bg-gray-100" x-data="{ sidebarOpen: true }">
    
    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'" 
           class="sidebar fixed left-0 top-0 h-full bg-green-800 text-white transition-all duration-300 z-40 no-print">
        
        <!-- Logo & Toggle -->
        <div class="flex items-center justify-between p-4 border-b border-green-700">
            <div x-show="sidebarOpen" x-cloak class="flex items-center space-x-2">
                <i class="fas fa-leaf text-2xl"></i>
                <span class="font-bold text-lg">POMS Report</span>
            </div>
            <button @click="sidebarOpen = !sidebarOpen" class="p-2 hover:bg-green-700 rounded">
                <i :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'" class="fas"></i>
            </button>
        </div>
        
        <!-- User Info -->
        <div class="p-4 border-b border-green-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center">
                    <i class="fas fa-user"></i>
                </div>
                <div x-show="sidebarOpen" x-cloak>
                    <p class="font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-green-300">{{ ucfirst(auth()->user()->role) }}</p>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="p-4 space-y-2">
            <!-- Dashboard -->
            <a href="{{ route('dashboard') }}" 
               class="flex items-center space-x-3 p-3 rounded {{ request()->routeIs('dashboard') ? 'bg-green-700' : 'hover:bg-green-700' }}">
                <i class="fas fa-home w-6"></i>
                <span x-show="sidebarOpen" x-cloak>Dashboard</span>
            </a>
            
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
                <button @click="open = !open" class="w-full flex items-center justify-between p-3 rounded hover:bg-green-700">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-industry w-6"></i>
                        <span x-show="sidebarOpen" x-cloak>Stasiun</span>
                    </div>
                    <i x-show="sidebarOpen" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-xs"></i>
                </button>
                <div x-show="open" x-cloak class="ml-9 mt-2 space-y-1">
                    @foreach($visibleStations as $stationKey => $stationLabel)
                    <a href="{{ route('stations.' . $stationKey) }}" class="block p-2 text-sm rounded hover:bg-green-700">{{ $stationLabel }}</a>
                    @endforeach
                </div>
            </div>
            @endif
            
            <!-- Flagged Records -->
            @can('view-flagged-records')
            <a href="{{ route('flagged.records') }}" 
               class="flex items-center space-x-3 p-3 rounded {{ request()->routeIs('flagged.records') ? 'bg-green-700' : 'hover:bg-green-700' }}">
                <i class="fas fa-flag w-6"></i>
                <span x-show="sidebarOpen" x-cloak>Data Flagged</span>
            </a>
            @endcan
            
            <!-- Analytics -->
            @can('access-full-dashboard')
            <div x-data="{ open: {{ request()->routeIs('analytics.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" class="w-full flex items-center justify-between p-3 rounded hover:bg-green-700">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chart-line w-6"></i>
                        <span x-show="sidebarOpen" x-cloak>Analytics</span>
                    </div>
                    <i x-show="sidebarOpen" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-xs"></i>
                </button>
                <div x-show="open" x-cloak class="ml-9 mt-2 space-y-1">
                    <a href="{{ route('analytics.overview') }}" class="block p-2 text-sm rounded hover:bg-green-700">Overview</a>
                    <a href="{{ route('analytics.losses') }}" class="block p-2 text-sm rounded hover:bg-green-700">Losses</a>
                    <a href="{{ route('analytics.efficiency') }}" class="block p-2 text-sm rounded hover:bg-green-700">Efficiency</a>
                </div>
            </div>
            @endcan
            
            <!-- HQ Multi-plant -->
            @can('view-all-plants')
            <div x-data="{ open: {{ request()->routeIs('hq.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" class="w-full flex items-center justify-between p-3 rounded hover:bg-green-700">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-building w-6"></i>
                        <span x-show="sidebarOpen" x-cloak>HQ</span>
                    </div>
                    <i x-show="sidebarOpen" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-xs"></i>
                </button>
                <div x-show="open" x-cloak class="ml-9 mt-2 space-y-1">
                    <a href="{{ route('hq.dashboard') }}" class="block p-2 text-sm rounded hover:bg-green-700">Dashboard</a>
                    <a href="{{ route('hq.comparison') }}" class="block p-2 text-sm rounded hover:bg-green-700">Perbandingan Plant</a>
                </div>
            </div>
            @endcan
            
            <!-- Settings -->
            @can('edit-validation-rules')
            <a href="{{ route('settings.validation-rules') }}" 
               class="flex items-center space-x-3 p-3 rounded {{ request()->routeIs('settings.*') ? 'bg-green-700' : 'hover:bg-green-700' }}">
                <i class="fas fa-cog w-6"></i>
                <span x-show="sidebarOpen" x-cloak>Settings</span>
            </a>
            @endcan
            
            <!-- Logout -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center space-x-3 p-3 rounded hover:bg-red-700 text-left">
                    <i class="fas fa-sign-out-alt w-6"></i>
                    <span x-show="sidebarOpen" x-cloak>Logout</span>
                </button>
            </form>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <div :class="sidebarOpen ? 'ml-64' : 'ml-20'" class="main-content transition-all duration-300 min-h-screen">
        
        <!-- Header -->
        <header class="bg-white shadow-sm p-4 no-print">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ $title ?? 'Dashboard' }}</h1>
                    <p class="text-sm text-gray-600">{{ $subtitle ?? 'Sistem Pelaporan Digital Pabrik Kelapa Sawit' }}</p>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">{{ auth()->user()->plant_id }}</span>
                    <span class="text-sm text-gray-600">{{ now()->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                </div>
            </div>
        </header>
        
        <!-- Flash Messages -->
        @if(session('success'))
        <div class="mx-4 mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded no-print">
            <i class="fas fa-check-circle mr-2"></i>
            {{ session('success') }}
        </div>
        @endif
        
        @if(session('error'))
        <div class="mx-4 mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded no-print">
            <i class="fas fa-exclamation-circle mr-2"></i>
            {{ session('error') }}
        </div>
        @endif
        
        @if(session('warning'))
        <div class="mx-4 mt-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded no-print">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            {{ session('warning') }}
        </div>
        @endif
        
        <!-- Page Content -->
        <main class="p-6">
            @yield('content')
        </main>
        
        <!-- Footer -->
        <footer class="bg-white border-t p-4 text-center text-sm text-gray-600 no-print">
            <p>&copy; {{ date('Y') }} POMS Report System. All rights reserved.</p>
        </footer>
    </div>
    
    @livewireScripts
    
    @stack('scripts')
    
</body>
</html>
