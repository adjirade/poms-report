<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - POMS Report</title>
    
    @vite('resources/css/app.css')
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-md">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full shadow-lg mb-4">
                <i class="fas fa-leaf text-4xl text-green-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">POMS Report</h1>
            <p class="text-white text-opacity-80">Sistem Pelaporan Digital Pabrik Kelapa Sawit</p>
        </div>
        
        <!-- Login Card -->
        <div class="bg-white rounded-lg shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Login</h2>
            
            <!-- Error Messages -->
            @if($errors->any())
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            
            <!-- Login Form -->
            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                
                <!-- Phone Number -->
                <div class="mb-4">
                    <label for="phone_number" class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-phone mr-2"></i>Nomor Telepon
                    </label>
                    <input type="text" 
                           id="phone_number" 
                           name="phone_number" 
                           value="{{ old('phone_number') }}"
                           placeholder="628123456789" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           required
                           autofocus>
                    <p class="text-xs text-gray-500 mt-1">Format: 628xxxxxxxxxx (tanpa +, tanpa spasi)</p>
                </div>
                
                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-lock mr-2"></i>Password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="••••••••" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           required>
                </div>
                
                <!-- Remember Me -->
                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="remember" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                        <span class="ml-2 text-gray-700">Ingat saya</span>
                    </label>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
            </form>
            
            @if(config('app.debug'))
            <!-- Info -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Akun bootstrap (hanya mode debug):</strong><br>
                    Phone: <code class="bg-blue-100 px-2 py-1 rounded">6281234567890</code><br>
                    Password: <span class="text-xs">lihat output <code>db:seed</code> atau env <code>ADMIN_INITIAL_PASSWORD</code></span>
                </p>
            </div>
            @endif
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8 text-white text-opacity-80 text-sm">
            <p>&copy; {{ date('Y') }} POMS Report System. All rights reserved.</p>
        </div>
    </div>
    
    
</body>
</html>
