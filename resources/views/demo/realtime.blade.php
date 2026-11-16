<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PulseForge — Real-Time Health & Incident War Room (Laravel Reverb)</title>

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

    <!-- Pusher & Laravel Echo CDNs -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
</head>
<body class="h-full flex flex-col font-sans antialiased bg-slate-950 text-slate-100">

    @if($currentUser->is_platform_admin)
        <!-- Super-Admin Workspace Inspection Banner -->
        <div class="bg-gradient-to-r from-purple-950 via-slate-900 to-purple-950 border-b border-purple-800/60 px-4 py-2 text-xs text-purple-200 flex flex-wrap items-center justify-between gap-2 z-50">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 uppercase tracking-wider">🛡️ Platform Admin Mode</span>
                <span>Auditing Workspace: <strong class="text-white">{{ $tenant->name }}</strong> (<code class="text-purple-300">{{ $tenant->slug }}</code>)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-slate-400">Switch Workspace:</span>
                <a href="/demo/realtime?tenant=acme" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition {{ $tenant->slug === 'acme' ? 'border border-purple-500 text-white font-bold' : '' }}">Acme Corp</a>
                <a href="/demo/realtime?tenant=cyberdyne" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition {{ $tenant->slug === 'cyberdyne' ? 'border border-purple-500 text-white font-bold' : '' }}">Cyberdyne</a>
                <a href="{{ route('admin.dashboard') }}" class="ml-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded bg-purple-600 hover:bg-purple-500 text-white font-medium shadow-sm transition">
                    <span>← Return to Platform Hub</span>
                </a>
            </div>
        </div>
    @endif

    <!-- Top Navigation Bar -->
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="h-9 w-9 rounded-lg bg-indigo-600 flex items-center justify-center font-black text-xl shadow-lg shadow-indigo-500/30">
                    ⚡
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                        PulseForge
                        <span class="text-xs font-mono font-medium px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">Reverb Live</span>
                    </h1>
                    <p class="text-xs text-slate-400">Workspace: <span class="text-slate-200 font-semibold">{{ $tenant->name }}</span> ({{ ucfirst($tenant->plan->value) }})</p>
                </div>
            </div>

            <!-- WebSocket Connection Status & Identity Switcher -->
            <div class="flex items-center space-x-4">
                <div id="ws-badge" class="flex items-center space-x-2 px-3 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <span id="ws-dot" class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                    <span id="ws-text">Connecting to Reverb (ws://localhost:8085)...</span>
                </div>

                <div class="flex items-center bg-slate-800/80 rounded-lg p-1 border border-slate-700/60 text-xs gap-2">
                    <div class="flex items-center gap-1.5 px-2">
                        @if($currentUser->avatar)
                            <img src="{{ $currentUser->avatar }}" alt="{{ $currentUser->name }}" class="h-5 w-5 rounded-full object-cover">
                        @else
                            <span class="h-5 w-5 rounded-full bg-indigo-600 text-white font-bold text-[10px] flex items-center justify-center">
                                {{ strtoupper(substr($currentUser->name, 0, 1)) }}
                            </span>
                        @endif
                        <div class="text-left">
                            <div class="font-semibold text-slate-200 truncate max-w-[120px] sm:max-w-none leading-tight">{{ $currentUser->name }}</div>
                            <div class="text-[10px] text-slate-400 font-mono truncate">{{ $currentUser->email }}</div>
                        </div>
                        @if($currentUser->is_platform_admin)
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-purple-500/20 text-purple-300 border border-purple-500/30 font-mono">Super Admin</span>
                        @else
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-700 text-slate-300 font-mono">{{ ucfirst($currentUser->roleForTenant($tenant)?->value ?? 'member') }}</span>
                        @endif
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

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- Live Demo Tip Banner -->
        <div class="rounded-xl bg-gradient-to-r from-indigo-950/60 to-purple-950/40 border border-indigo-800/40 p-4 flex items-start space-x-3">
            <span class="text-2xl">💡</span>
            <div class="text-sm">
                <span class="font-semibold text-indigo-300">Multi-User Real-Time Collaboration:</span>
                <span class="text-slate-300">
                    Open an Incognito or 2nd browser window, log in as <span class="font-mono text-xs bg-slate-800 px-1.5 py-0.5 rounded text-indigo-300">khrehmanwaheed@gmail.com</span> (Acme Admin), and test live heartbeats and incident war room chat simultaneously!
                </span>
            </div>
        </div>

        <!-- Section 1: Monitored Services & Heartbeats -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                    <span>📡 Monitored Endpoints & Real-Time Heartbeats</span>
                </h2>
                <span class="text-xs text-slate-500 font-mono">Listening on private-tenant.{{ $tenant->id }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($monitors as $monitor)
                    <div id="monitor-card-{{ $monitor->id }}" class="bg-slate-900/90 rounded-xl p-4 border border-slate-800 transition duration-300 hover:border-slate-700 relative overflow-hidden">
                        <div id="pulse-bg-{{ $monitor->id }}" class="absolute inset-0 bg-emerald-500/0 pointer-events-none transition duration-500"></div>
                        
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-semibold text-white text-sm truncate" title="{{ $monitor->name }}">{{ $monitor->name }}</span>
                            <span id="monitor-badge-{{ $monitor->id }}" class="px-2 py-0.5 rounded text-xs font-semibold {{ $monitor->status->value === 'healthy' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                {{ strtoupper($monitor->status->value) }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-400 font-mono truncate mb-3">{{ $monitor->url }}</p>

                        <div class="flex items-center justify-between text-xs pt-3 border-t border-slate-800/80">
                            <div>
                                <span class="text-slate-500">Latency:</span>
                                <span id="monitor-latency-{{ $monitor->id }}" class="font-mono font-bold text-white">-- ms</span>
                            </div>
                            <button onclick="triggerPing({{ $monitor->id }})" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-indigo-600 text-slate-200 hover:text-white font-medium transition text-xs flex items-center gap-1 active:scale-95">
                                <span>⚡ Ping</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Section 2: Live Probe Telemetry & Check History -->
        <div class="bg-slate-900/90 rounded-2xl border border-slate-800 overflow-hidden shadow-2xl">
            <div class="p-4 bg-slate-850 border-b border-slate-800 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center space-x-3">
                    <div class="h-8 w-8 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-lg border border-indigo-500/30">
                        📊
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-white">Live Telemetry & Probe Check History</h3>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-semibold flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-ping"></span> Live Stream
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">
                            Workspace: <span class="font-semibold text-slate-200">{{ $tenant->name }}</span> (<code class="text-indigo-300">private-tenant.{{ $tenant->id }}</code>) &bull; Auto-updates in real time via Reverb
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-mono px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 text-slate-300">
                        Scoped to {{ $tenant->name }}
                    </span>
                </div>
            </div>

            <!-- Table Container -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/70 text-slate-400 uppercase font-mono text-[11px] tracking-wider">
                            <th class="py-3 px-4">Time</th>
                            <th class="py-3 px-4">Monitored Service</th>
                            <th class="py-3 px-4">HTTP Status</th>
                            <th class="py-3 px-4">Latency</th>
                            <th class="py-3 px-4">SSL Cert</th>
                            <th class="py-3 px-4">Result</th>
                        </tr>
                    </thead>
                    <tbody id="check-logs-tbody" class="divide-y divide-slate-800/60 font-sans">
                        @forelse($checkLogs as $log)
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 text-slate-400 font-mono whitespace-nowrap">
                                    <span title="{{ $log->checked_at->toIso8601String() }}">{{ $log->checked_at->diffForHumans() }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-slate-200">{{ $log->monitor?->name ?? 'Service #' . $log->monitor_id }}</div>
                                    <div class="font-mono text-[10px] text-slate-500 truncate max-w-xs">{{ $log->monitor?->url }}</div>
                                </td>
                                <td class="py-3 px-4 font-mono">
                                    @if($log->status_code)
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $log->status_code < 400 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                            {{ $log->status_code }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            ERR / TIMEOUT
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-mono font-semibold {{ ($log->response_time_ms ?? 0) < 300 ? 'text-emerald-400' : (($log->response_time_ms ?? 0) < 800 ? 'text-amber-400' : 'text-rose-400') }}">
                                    {{ $log->response_time_ms !== null ? $log->response_time_ms . ' ms' : '--' }}
                                </td>
                                <td class="py-3 px-4 font-mono">
                                    @if($log->ssl_is_valid === true)
                                        <span class="text-emerald-400 flex items-center gap-1">
                                            <span>🔒</span> Valid {{ $log->ssl_days_remaining ? '(' . $log->ssl_days_remaining . 'd)' : '' }}
                                        </span>
                                    @elseif($log->ssl_is_valid === false)
                                        <span class="text-rose-400 flex items-center gap-1">
                                            <span>⚠️</span> Invalid
                                        </span>
                                    @else
                                        <span class="text-slate-500">N/A</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($log->is_successful)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            ✓ PASS
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20" title="{{ $log->error_message }}">
                                            ✕ FAIL
                                        </span>
                                        @if($log->error_message)
                                            <div class="text-[10px] text-rose-300/80 font-mono truncate max-w-xs mt-0.5">{{ $log->error_message }}</div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr id="no-check-logs">
                                <td colspan="6" class="py-8 text-center text-slate-500 font-mono text-xs">
                                    No probe checks recorded yet for {{ $tenant->name }}. Click <span class="text-indigo-400 font-semibold">⚡ Ping</span> above to trigger a live check!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 3: Incident War Room Live Chat -->
        @if($incident !== null)
            <div class="bg-slate-900/90 rounded-2xl border border-slate-800 overflow-hidden flex flex-col shadow-2xl">
                <!-- War Room Header -->
                <div class="p-4 bg-slate-850 border-b border-slate-800 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center space-x-3">
                        <div class="h-8 w-8 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-lg border border-rose-500/30">
                            🚨
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-white">{{ $incident->title }}</h3>
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-rose-500/10 text-rose-400 border border-rose-500/20 uppercase font-semibold">
                                    {{ $incident->severity->value }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400">
                                War Room Channel: <span class="font-mono text-indigo-300">private-tenant.{{ $tenant->id }}.incident.{{ $incident->id }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="text-xs text-slate-400 font-mono">
                        Started: {{ $incident->started_at->diffForHumans() }}
                    </div>
                </div>

                <!-- Chat Stream -->
                <div id="chat-messages" class="h-72 overflow-y-auto p-4 space-y-3 bg-slate-950/60 divide-y divide-slate-900">
                    @forelse($messages as $msg)
                        <div class="pt-2 first:pt-0 flex items-start space-x-3">
                            <div class="h-8 w-8 rounded-full bg-indigo-600/30 text-indigo-300 border border-indigo-500/40 flex items-center justify-center text-xs font-bold shrink-0">
                                {{ strtoupper(substr($msg->user->name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-baseline space-x-2">
                                    <span class="text-xs font-semibold text-slate-200">{{ $msg->user->name }}</span>
                                    <span class="text-[10px] text-slate-500">{{ $msg->created_at?->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-slate-300 break-words mt-0.5">{{ $msg->message }}</p>
                            </div>
                        </div>
                    @empty
                        <div id="no-messages" class="text-center py-10 text-slate-500 text-xs">
                            No war room messages yet. Send a message below to broadcast live to all connected engineers!
                        </div>
                    @endforelse
                </div>

                <!-- Chat Input Form -->
                <form id="chat-form" onsubmit="sendMessage(event)" class="p-3 bg-slate-900 border-t border-slate-800 flex items-center space-x-2">
                    <input 
                        type="text" 
                        id="chat-input" 
                        placeholder="Post update to war room (e.g. 'Investigating redis pool', 'Restarting service')..." 
                        class="flex-1 bg-slate-950 border border-slate-700/80 rounded-lg px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
                        autocomplete="off"
                        required
                    />
                    <button 
                        type="submit" 
                        id="send-btn"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition active:scale-95 flex items-center gap-1.5 shrink-0"
                    >
                        <span>Send</span> 💬
                    </button>
                </form>
            </div>
        @endif

    </main>

    <!-- JavaScript: Laravel Echo & Reverb Connection -->
    <script>
        const tenantId = {{ $tenant->id }};
        const incidentId = {{ $incident->id ?? 'null' }};
        const currentUserId = {{ $currentUser->id }};
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Play synthetic notification sound via Web Audio API
        function playChime(freq = 600) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, ctx.currentTime);
                gain.gain.setValueAtTime(0.05, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.3);
            } catch (e) {}
        }

        // Initialize Laravel Echo with Reverb
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: '{{ $reverbKey }}',
            wsHost: '{{ $reverbHost }}',
            wsPort: {{ $reverbPort }},
            wssPort: {{ $reverbPort }},
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Tenant-Slug': '{{ $tenant->slug }}'
                }
            }
        });

        // Monitor Connection State
        const wsBadge = document.getElementById('ws-badge');
        const wsDot = document.getElementById('ws-dot');
        const wsText = document.getElementById('ws-text');

        window.Echo.connector.pusher.connection.bind('connected', () => {
            wsBadge.className = "flex items-center space-x-2 px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20";
            wsDot.className = "h-2 w-2 rounded-full bg-emerald-400";
            wsText.innerText = "Reverb Connected (Port 8085)";
            playChime(880);
        });

        window.Echo.connector.pusher.connection.bind('disconnected', () => {
            wsBadge.className = "flex items-center space-x-2 px-3 py-1 rounded-full text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20";
            wsDot.className = "h-2 w-2 rounded-full bg-rose-400";
            wsText.innerText = "Reverb Disconnected";
        });

        // 1. Subscribe to Tenant Private Channel for Live Health Heartbeats
        window.Echo.private(`tenant.${tenantId}`)
            .listen('.MonitorChecked', (e) => {
                const monitorId = e.monitor_id;
                const latencyEl = document.getElementById(`monitor-latency-${monitorId}`);
                const badgeEl = document.getElementById(`monitor-badge-${monitorId}`);
                const pulseEl = document.getElementById(`pulse-bg-${monitorId}`);

                if (latencyEl) {
                    latencyEl.innerText = `${e.response_time_ms} ms`;
                    latencyEl.className = e.is_successful ? "font-mono font-bold text-emerald-400" : "font-mono font-bold text-rose-400";
                }

                if (badgeEl) {
                    badgeEl.innerText = e.is_successful ? "HEALTHY" : "DOWN";
                    badgeEl.className = e.is_successful 
                        ? "px-2 py-0.5 rounded text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                        : "px-2 py-0.5 rounded text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20";
                }

                if (pulseEl) {
                    pulseEl.className = e.is_successful 
                        ? "absolute inset-0 bg-emerald-500/20 pointer-events-none transition duration-500"
                        : "absolute inset-0 bg-rose-500/30 pointer-events-none transition duration-500";
                    setTimeout(() => {
                        pulseEl.className = "absolute inset-0 bg-transparent pointer-events-none transition duration-500";
                    }, 500);
                }

                appendTelemetryLog(e);
                playChime(e.is_successful ? 520 : 300);
            });

        // 2. Subscribe to Incident War Room Private Channel for Live Chat
        if (incidentId) {
            window.Echo.private(`tenant.${tenantId}.incident.${incidentId}`)
                .listen('.IncidentChatMessageSent', (e) => {
                    appendMessage(e.user_name, e.message, 'Just now', e.user_id === currentUserId);
                    playChime(660);
                });
        }

        // Prepend incoming telemetry check result to UI table
        function appendTelemetryLog(e) {
            const noLogsEl = document.getElementById('no-check-logs');
            if (noLogsEl) noLogsEl.remove();

            const tbody = document.getElementById('check-logs-tbody');
            if (!tbody) return;

            const tr = document.createElement('tr');
            tr.className = `transition duration-500 ${e.is_successful ? 'bg-emerald-500/20' : 'bg-rose-500/25'}`;

            const latencyClass = (e.response_time_ms || 0) < 300 ? 'text-emerald-400' : ((e.response_time_ms || 0) < 800 ? 'text-amber-400' : 'text-rose-400');
            const statusBadge = e.status_code 
                ? `<span class="px-2 py-0.5 rounded text-[11px] font-semibold ${e.status_code < 400 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'}">${e.status_code}</span>`
                : `<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">ERR / TIMEOUT</span>`;

            let sslBadge = '<span class="text-slate-500">N/A</span>';
            if (e.ssl_is_valid === true) {
                sslBadge = `<span class="text-emerald-400 flex items-center gap-1"><span>🔒</span> Valid ${e.ssl_days_remaining ? '(' + e.ssl_days_remaining + 'd)' : ''}</span>`;
            } else if (e.ssl_is_valid === false) {
                sslBadge = '<span class="text-rose-400 flex items-center gap-1"><span>⚠️</span> Invalid</span>';
            }

            const outcomeBadge = e.is_successful
                ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">✓ PASS</span>'
                : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20" title="${escapeHtml(e.error_message || '')}">✕ FAIL</span>${e.error_message ? `<div class="text-[10px] text-rose-300/80 font-mono truncate max-w-xs mt-0.5">${escapeHtml(e.error_message)}</div>` : ''}`;

            tr.innerHTML = `
                <td class="py-3 px-4 text-slate-400 font-mono whitespace-nowrap">
                    <span class="text-indigo-300 font-semibold">${escapeHtml(e.checked_at_human || 'Just now')}</span>
                </td>
                <td class="py-3 px-4">
                    <div class="font-medium text-slate-200">${escapeHtml(e.monitor_name || 'Service #' + e.monitor_id)}</div>
                    <div class="font-mono text-[10px] text-slate-500 truncate max-w-xs">${escapeHtml(e.monitor_url || '')}</div>
                </td>
                <td class="py-3 px-4 font-mono">${statusBadge}</td>
                <td class="py-3 px-4 font-mono font-semibold ${latencyClass}">${e.response_time_ms !== null && e.response_time_ms !== undefined ? e.response_time_ms + ' ms' : '--'}</td>
                <td class="py-3 px-4 font-mono">${sslBadge}</td>
                <td class="py-3 px-4 whitespace-nowrap">${outcomeBadge}</td>
            `;

            tbody.insertBefore(tr, tbody.firstChild);

            setTimeout(() => {
                tr.className = 'hover:bg-slate-800/40 transition duration-500';
            }, 1500);

            while (tbody.children.length > 15) {
                tbody.removeChild(tbody.lastChild);
            }
        }

        // Append message to UI
        function appendMessage(userName, message, timeStr, isMe = false) {
            const noMsgEl = document.getElementById('no-messages');
            if (noMsgEl) noMsgEl.remove();

            const chatMessages = document.getElementById('chat-messages');
            const initials = userName.substring(0, 2).toUpperCase();

            const msgDiv = document.createElement('div');
            msgDiv.className = `pt-2 flex items-start space-x-3 ${isMe ? 'bg-indigo-950/20 rounded-lg p-2' : ''}`;
            msgDiv.innerHTML = `
                <div class="h-8 w-8 rounded-full ${isMe ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-indigo-300 border border-indigo-500/30'} flex items-center justify-center text-xs font-bold shrink-0">
                    ${initials}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline space-x-2">
                        <span class="text-xs font-semibold ${isMe ? 'text-indigo-300' : 'text-slate-200'}">${userName} ${isMe ? '<span class="text-[10px] text-slate-500 font-normal">(You)</span>' : ''}</span>
                        <span class="text-[10px] text-slate-500">${timeStr}</span>
                    </div>
                    <p class="text-sm text-slate-300 break-words mt-0.5">${escapeHtml(message)}</p>
                </div>
            `;

            chatMessages.appendChild(msgDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function escapeHtml(str) {
            const p = document.createElement('p');
            p.textContent = str;
            return p.innerHTML;
        }

        // Send chat message via AJAX
        async function sendMessage(e) {
            e.preventDefault();
            const input = document.getElementById('chat-input');
            const btn = document.getElementById('send-btn');
            const message = input.value.trim();

            if (!message) return;

            input.disabled = true;
            btn.disabled = true;

            try {
                const res = await fetch('/demo/realtime/messages', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        incident_id: incidentId,
                        message: message
                    })
                });

                if (res.ok) {
                    input.value = '';
                }
            } catch (err) {
                console.error('Failed to send message:', err);
            } finally {
                input.disabled = false;
                btn.disabled = false;
                input.focus();
            }
        }

        // Trigger Live Ping Check via AJAX
        async function triggerPing(monitorId) {
            try {
                await fetch('/demo/realtime/trigger-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ monitor_id: monitorId })
                });
            } catch (err) {
                console.error('Failed to trigger ping:', err);
            }
        }
    </script>
</body>
</html>

