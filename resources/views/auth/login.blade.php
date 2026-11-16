<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — PulseForge</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full flex items-center justify-center font-sans antialiased bg-slate-950 text-slate-100 p-4">

    <div class="max-w-md w-full space-y-6">
        <!-- Logo & Branding -->
        <div class="text-center space-y-2">
            <div class="inline-flex h-12 w-12 rounded-xl bg-indigo-600 items-center justify-center font-black text-2xl shadow-xl shadow-indigo-500/30">
                ⚡
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">PulseForge</h1>
            <p class="text-xs text-slate-400">Distributed Health Monitoring & Incident War Room</p>
        </div>

        <!-- Main Card -->
        <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 sm:p-8 shadow-2xl backdrop-blur">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-white">Sign In to Your Workspace</h2>
                <p class="text-xs text-slate-400 mt-1">Authenticate to access real-time telemetry and incident control.</p>
            </div>

            <!-- Error Alerts -->
            @if($errors->any())
                <div class="mb-5 p-3.5 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs space-y-1">
                    @foreach($errors->all() as $error)
                        <div class="flex items-center gap-1.5">
                            <span>✕</span>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Standard Email / Password Form -->
            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-300 mb-1.5">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        value="{{ old('email') }}" 
                        placeholder="you@company.com" 
                        required 
                        autofocus
                        class="w-full bg-slate-950 border border-slate-700/80 rounded-lg px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition font-sans"
                    />
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold text-slate-300">Password</label>
                    </div>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        placeholder="••••••••" 
                        required 
                        class="w-full bg-slate-950 border border-slate-700/80 rounded-lg px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition font-sans"
                    />
                </div>

                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center space-x-2 text-slate-400 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500/20">
                        <span>Remember this device</span>
                    </label>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition duration-150 active:scale-[0.99] shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2"
                >
                    <span>Sign In</span>
                    <span>→</span>
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-800"></div>
                </div>
                <div class="relative flex justify-center text-[11px] uppercase tracking-wider font-mono">
                    <span class="bg-slate-900 px-3 text-slate-500">Or continue with</span>
                </div>
            </div>

            <!-- Google OAuth Button -->
            <a 
                href="{{ route('auth.google') }}" 
                class="w-full py-2.5 px-4 bg-slate-950 hover:bg-slate-800 border border-slate-700/80 hover:border-slate-600 text-slate-200 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2.5 shadow-sm"
            >
                <!-- Google "G" SVG -->
                <svg class="h-4 w-4" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.65v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.14z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.24v3.15C3.26 21.36 7.33 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.24C.45 8.15 0 9.99 0 12s.45 3.85 1.24 5.42l4.04-3.15z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.24 6.58l4.04 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                </svg>
                <span>Sign in with Google</span>
            </a>
        </div>

        <!-- Quick-Fill Seed Accounts Card (Demo Helper) -->
        <div class="rounded-xl bg-slate-900/60 border border-slate-800/80 p-4 space-y-2.5">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span class="font-semibold text-slate-300">⚡ Demo Quick-Fill Credentials</span>
                <span class="font-mono text-[11px] text-slate-500">password: <code class="text-indigo-400">password</code></span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <button 
                    type="button" 
                    onclick="fillAccount('learner.den5556@gmail.com', 'password')"
                    class="p-2 rounded bg-slate-950 hover:bg-indigo-950/40 border border-slate-800 hover:border-indigo-500/40 text-left transition group"
                >
                    <div class="text-[11px] font-bold text-white group-hover:text-indigo-300">Acme Owner</div>
                    <div class="text-[10px] text-slate-400 truncate">learner.den5556</div>
                </button>

                <button 
                    type="button" 
                    onclick="fillAccount('khrehmanwaheed@gmail.com', 'password')"
                    class="p-2 rounded bg-slate-950 hover:bg-indigo-950/40 border border-slate-800 hover:border-indigo-500/40 text-left transition group"
                >
                    <div class="text-[11px] font-bold text-white group-hover:text-indigo-300">Acme Admin</div>
                    <div class="text-[10px] text-slate-400 truncate">khrehmanwaheed</div>
                </button>

                <button 
                    type="button" 
                    onclick="fillAccount('rehman5556@gmail.com', 'password')"
                    class="p-2 rounded bg-slate-950 hover:bg-indigo-950/40 border border-slate-800 hover:border-indigo-500/40 text-left transition group"
                >
                    <div class="text-[11px] font-bold text-white group-hover:text-indigo-300">Super Admin</div>
                    <div class="text-[10px] text-slate-400 truncate">rehman5556</div>
                </button>
            </div>
        </div>
    </div>

    <script>
        function fillAccount(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            document.getElementById('email').focus();
        }
    </script>
</body>
</html>

