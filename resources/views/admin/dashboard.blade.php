<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PulseForge — Platform Super-Admin Control Center</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                        mono: ['JetBrains Mono', 'Fira Code', 'monospace']
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-full flex flex-col font-sans antialiased bg-slate-950 text-slate-100">

    <!-- Top Navigation Header -->
    <header class="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center font-black text-xl shadow-lg shadow-purple-500/20 text-white">
                    🛡️
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                        PulseForge
                        <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30">Platform Super-Admin</span>
                    </h1>
                    <p class="text-xs text-slate-400">Global Multi-Tenant Control Center & Supervision Hub</p>
                </div>
            </div>

            <!-- Identity and Logout -->
            <div class="flex items-center space-x-3">
                <div class="flex items-center bg-slate-800/80 rounded-lg p-1.5 border border-slate-700/60 text-xs gap-3">
                    <div class="flex items-center gap-2 px-1">
                        @if($currentUser->avatar)
                            <img src="{{ $currentUser->avatar }}" alt="{{ $currentUser->name }}" class="h-6 w-6 rounded-full object-cover ring-1 ring-purple-500/50">
                        @else
                            <span class="h-6 w-6 rounded-full bg-purple-600 text-white font-bold text-xs flex items-center justify-center">
                                {{ strtoupper(substr($currentUser->name, 0, 1)) }}
                            </span>
                        @endif
                        <div class="text-left">
                            <div class="font-semibold text-slate-200 leading-tight">{{ $currentUser->name }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ $currentUser->email }}</div>
                        </div>
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 font-mono uppercase tracking-wider">Super Admin</span>
                    </div>

                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-2.5 py-1 rounded bg-slate-700/80 hover:bg-rose-600 text-slate-300 hover:text-white font-medium transition flex items-center gap-1 text-xs">
                            <span>Sign Out</span> 🚪
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Top Metrics Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Card 1: Total Tenants -->
            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Tenant Workspaces</span>
                    <span class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400 text-lg">🏢</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-white tracking-tight">{{ $totalTenants }}</span>
                    <span class="text-xs text-emerald-400 font-medium">({{ $activeTenants }} Active)</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Isolated customer organizations</p>
            </div>

            <!-- Card 2: Total Monitors -->
            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Active Probes</span>
                    <span class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-lg">📡</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-white tracking-tight">{{ $totalMonitors }}</span>
                    <span class="text-xs text-emerald-400 font-medium">✓ {{ $healthyMonitors }} Healthy</span>
                </div>
                <div class="mt-1 flex items-center gap-2 text-xs">
                    @if($failingMonitors > 0)
                        <span class="text-rose-400 font-medium">⚠️ {{ $failingMonitors }} Failing</span>
                    @else
                        <span class="text-slate-500">0 probe failures</span>
                    @endif
                </div>
            </div>

            <!-- Card 3: Active Incidents -->
            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Open Incidents</span>
                    <span class="p-2 rounded-lg bg-rose-500/10 text-rose-400 text-lg">🚨</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold {{ $openIncidentsCount > 0 ? 'text-rose-400' : 'text-emerald-400' }} tracking-tight">
                        {{ $openIncidentsCount }}
                    </span>
                    <span class="text-xs text-slate-400">across all tenants</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $openIncidentsCount > 0 ? 'War rooms actively investigating' : 'All systems operating normally' }}
                </p>
            </div>

            <!-- Card 4: Platform Engine -->
            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Real-Time Engine</span>
                    <span class="p-2 rounded-lg bg-purple-500/10 text-purple-400 text-lg">⚡</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-sm font-bold text-purple-300 font-mono">Laravel Reverb</span>
                    <span class="text-xs px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">Port 8085</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Redis Cache & Pessimistic Locks Active</p>
            </div>

        </div>

        <!-- Tenant Workspaces Directory Section -->
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-white tracking-tight flex items-center gap-2">
                        <span>Customer Workspaces Directory</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">{{ $tenants->count() }} registered</span>
                    </h2>
                    <p class="text-xs text-slate-400">Supervise tenants, monitor quota enforcement, and inspect workspaces live.</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/40 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/90 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                            <tr>
                                <th scope="col" class="py-3 px-4 font-semibold">Tenant Organization</th>
                                <th scope="col" class="py-3 px-4 font-semibold">Slug Identifier</th>
                                <th scope="col" class="py-3 px-4 font-semibold">Subscription Plan</th>
                                <th scope="col" class="py-3 px-4 font-semibold">Monitors / Quota</th>
                                <th scope="col" class="py-3 px-4 font-semibold">Team Members</th>
                                <th scope="col" class="py-3 px-4 font-semibold">Status</th>
                                <th scope="col" class="py-3 px-4 font-semibold text-right">Supervision Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @foreach($tenants as $t)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="py-3.5 px-4 font-medium text-white flex items-center gap-2">
                                        <div class="h-7 w-7 rounded bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-slate-300">
                                            {{ strtoupper(substr($t->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-100">{{ $t->name }}</div>
                                            <div class="text-[10px] text-slate-500 font-mono">Tenant ID: #{{ $t->id }}</div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-purple-300">
                                        {{ $t->slug }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if($t->plan->value === 'enterprise')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 uppercase">Enterprise</span>
                                        @elseif($t->plan->value === 'pro')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 uppercase">Pro Tier</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-700 text-slate-300 uppercase">Free Tier</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="font-bold text-white">{{ $t->monitors_count }}</span>
                                        <span class="text-slate-500">/ {{ $t->monitor_limit }} quota</span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="font-mono text-slate-300">{{ $t->users_count }} users</span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if($t->is_active)
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span> Suspended
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <a href="/demo/realtime?tenant={{ $t->slug }}"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600/90 hover:bg-indigo-500 text-white font-medium text-xs shadow-sm transition">
                                            <span>Inspect Live Workspace</span> 👁️
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Global Telemetry & Health Checks Stream -->
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-white tracking-tight flex items-center gap-2">
                        <span>Latest Global Telemetry Checks</span>
                        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    </h2>
                    <p class="text-xs text-slate-400">Real-time health probe stream captured across all workspaces.</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/40 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/90 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                            <tr>
                                <th scope="col" class="py-3 px-4 font-semibold">Timestamp</th>
                                <th scope="col" class="py-3 px-4 font-semibold">Tenant</th>
                                <th scope="col" class="py-3 px-4 font-semibold">Monitored Service</th>
                                <th scope="col" class="py-3 px-4 font-semibold">HTTP Status</th>
                                <th scope="col" class="py-3 px-4 font-semibold">Response Time</th>
                                <th scope="col" class="py-3 px-4 font-semibold">SSL Status</th>
                                <th scope="col" class="py-3 px-4 font-semibold text-right">Outcome</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse($recentChecks as $check)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="py-2.5 px-4 font-mono text-[11px] text-slate-400">
                                        {{ $check->checked_at->format('H:i:s') }}
                                    </td>
                                    <td class="py-2.5 px-4 font-medium text-purple-300 font-mono">
                                        {{ $check->tenant?->name ?? 'Tenant #' . $check->tenant_id }}
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <div class="font-medium text-slate-200">{{ $check->monitor?->name ?? 'Unknown Service' }}</div>
                                        <div class="text-[10px] text-slate-500 font-mono truncate max-w-xs">{{ $check->monitor?->url }}</div>
                                    </td>
                                    <td class="py-2.5 px-4">
                                        @if($check->status_code)
                                            <span class="font-mono font-bold {{ $check->status_code < 400 ? 'text-emerald-400' : 'text-rose-400' }}">
                                                HTTP {{ $check->status_code }}
                                            </span>
                                        @else
                                            <span class="text-slate-500 font-mono">N/A</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-4 font-mono text-slate-300">
                                        {{ $check->response_time_ms ? $check->response_time_ms . ' ms' : '--' }}
                                    </td>
                                    <td class="py-2.5 px-4 text-slate-400">
                                        @if($check->ssl_is_valid)
                                            <span class="text-emerald-400">Valid</span> ({{ $check->ssl_days_remaining }}d)
                                        @else
                                            <span class="text-slate-500">--</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-4 text-right">
                                        @if($check->is_successful)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                PASSED
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                FAILED
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-500 text-xs">
                                        No recent telemetry checks recorded. Trigger a ping from any tenant workspace to begin streaming.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-900 bg-slate-950 py-4 mt-8">
        <div class="max-w-7xl mx-auto px-4 text-center text-xs text-slate-600">
            PulseForge Multi-Tenant Platform &bull; Super-Admin Engine v1.0 &bull; Running on PHP 8.4 & Laravel 13
        </div>
    </footer>

</body>
</html>

