<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lupa Password - POMS Report</title>
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
        <div class="mb-8 text-center">
            <div class="glass-sheen relative mb-4 inline-flex h-20 w-20 items-center justify-center rounded-4xl border border-white/25 bg-white/10 shadow-glass-lg backdrop-blur-2xl">
                <i class="fas fa-key text-4xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-white text-shadow-glass">Lupa Password</h1>
            <p class="mt-1 text-sm text-white/70">Tautan reset dikirim ke Telegram Anda</p>
        </div>

        <div class="glass glass-sheen overflow-hidden">
            <div class="card-pad">
                @if(session('status'))
                <div role="status" class="mb-5 flex items-start gap-3 rounded-2xl border border-green-300/60 bg-green-100/70 p-4 text-sm text-green-900 backdrop-blur-xl">
                    <i class="fas fa-check-circle mt-0.5 text-green-600"></i>
                    <p>{{ session('status') }}</p>
                </div>
                @endif

                @if($errors->any())
                <div role="alert" class="mb-5 rounded-2xl border border-red-300/60 bg-red-100/70 p-4 text-sm text-red-900 backdrop-blur-xl">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
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
                        <p class="mt-2 text-xs text-gray-500">
                            Masukkan nomor yang dipakai login. Tautan reset dikirim via bot Telegram
                            (pastikan Anda pernah mengirim pesan ke bot).
                        </p>
                    </div>

                    <button type="submit" class="btn-primary w-full py-3 text-base">
                        <i class="fas fa-paper-plane"></i>Kirim Tautan Reset
                    </button>
                </form>

                <div class="mt-6 text-center text-sm">
                    <a href="{{ route('login') }}" class="font-medium text-green-700 hover:text-green-800">
                        <i class="fas fa-arrow-left mr-1"></i>Kembali ke Login
                    </a>
                </div>
            </div>
        </div>

        <p class="mt-8 text-center text-sm text-white/70">&copy; {{ date('Y') }} POMS Report System.</p>
    </div>

</body>
</html>
