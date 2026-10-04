<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - POMS Report</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%2315803d'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle'%3E%F0%9F%8C%BF%3C/text%3E%3C/svg%3E">

    @vite('resources/css/app.css')

    <style>
        body {
            background-image:
                radial-gradient(900px 620px at 8% -6%, rgba(16, 185, 129, 0.55), transparent 60%),
                radial-gradient(820px 560px at 100% 8%, rgba(45, 212, 191, 0.45), transparent 58%),
                radial-gradient(760px 560px at 40% 115%, rgba(132, 204, 22, 0.4), transparent 60%),
                linear-gradient(160deg, #04241a 0%, #064e3b 48%, #0f766e 100%);
            background-attachment: fixed;
        }
    </style>
</head>
<body class="flex min-h-screen items-center justify-center p-4">

    <div class="w-full max-w-md animate-fade-up">
        <!-- Logo & Title -->
        <div class="mb-8 text-center">
            <div class="glass-sheen relative mb-4 inline-flex h-20 w-20 items-center justify-center rounded-4xl border border-white/25 bg-white/10 shadow-glass-lg backdrop-blur-2xl">
                <i class="fas fa-leaf text-4xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-white text-shadow-glass">POMS Report</h1>
            <p class="mt-1 text-sm text-white/70">Sistem Pelaporan Digital Pabrik Kelapa Sawit</p>
        </div>

        <!-- Login Card -->
        <div class="glass glass-sheen overflow-hidden">
            <div class="card-pad">
                <h2 class="mb-6 text-center text-xl font-semibold text-gray-800">Masuk ke akun Anda</h2>

                @if(session('success'))
                <div role="status" class="mb-4 flex items-start gap-3 rounded-2xl border border-green-300/60 bg-green-100/70 p-4 text-sm text-green-900 backdrop-blur-xl">
                    <i class="fas fa-check-circle mt-0.5 text-green-600"></i>
                    <p>{{ session('success') }}</p>
                </div>
                @endif

                @if($errors->any())
                <div role="alert" class="mb-4 rounded-2xl border border-red-300/60 bg-red-100/70 p-4 text-sm text-red-900 backdrop-blur-xl">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="phone_number" class="mb-2 block text-sm font-medium text-gray-700">
                            <i class="fas fa-phone mr-2 text-green-600"></i>Nomor Telepon
                        </label>
                        <input type="text"
                               id="phone_number"
                               name="phone_number"
                               value="{{ old('phone_number') }}"
                               placeholder="628123456789"
                               inputmode="numeric"
                               class="input text-base"
                               required
                               autofocus>
                        <p class="mt-2 text-xs text-gray-500">Format: 628xxxxxxxxxx (tanpa +, tanpa spasi)</p>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-lock mr-2 text-green-600"></i>Password
                            </label>
                            <a href="{{ route('password.request') }}" class="text-sm font-medium text-green-700 hover:text-green-800">
                                Lupa password?
                            </a>
                        </div>
                        <div class="relative">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   placeholder="••••••••"
                                   class="input pr-12 text-base"
                                   required>
                            <button type="button" data-toggle-password="password"
                                    class="absolute right-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-xl text-gray-400 transition hover:bg-white/60 hover:text-gray-600"
                                    aria-label="Tampilkan atau sembunyikan password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                        <span>Ingat saya</span>
                    </label>

                    <button type="submit" class="btn-primary w-full py-3 text-base">
                        <i class="fas fa-sign-in-alt"></i>Login
                    </button>
                </form>

                @if(config('app.debug'))
                <div class="mt-6 rounded-2xl border border-sky-200/60 bg-sky-50/70 p-4 text-sm text-sky-900 backdrop-blur-xl">
                    <p class="font-semibold"><i class="fas fa-info-circle mr-2"></i>Akun bootstrap (hanya mode debug)</p>
                    <p class="mt-1">Phone: <code class="rounded bg-white/70 px-2 py-0.5">6281234567890</code></p>
                    <p class="text-xs text-sky-700">Password: lihat output <code>db:seed</code> atau env <code>ADMIN_INITIAL_PASSWORD</code></p>
                </div>
                @endif
            </div>
        </div>

        <p class="mt-8 text-center text-sm text-white/70">
            &copy; {{ date('Y') }} POMS Report System. All rights reserved.
        </p>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.togglePassword);
                var icon = btn.querySelector('i');
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
            });
        });
    </script>

</body>
</html>
