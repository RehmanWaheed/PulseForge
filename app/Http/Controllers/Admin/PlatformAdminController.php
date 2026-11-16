<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheckResult;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    /**
     * Display the PulseForge Platform Super-Admin Control Center.
     */
    public function index(Request $request): View
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        $totalTenants = Tenant::query()->count();
        $activeTenants = Tenant::query()->where('is_active', true)->count();

        $totalMonitors = Monitor::withoutGlobalScopes()->count();
        $healthyMonitors = Monitor::withoutGlobalScopes()
            ->where('status', MonitorStatus::Healthy)
            ->count();
        $failingMonitors = Monitor::withoutGlobalScopes()
            ->whereIn('status', [MonitorStatus::Down, MonitorStatus::Degraded])
            ->count();

        $openIncidentsCount = Incident::withoutGlobalScopes()
            ->whereIn('status', [IncidentStatus::Investigating, IncidentStatus::Identified])
            ->count();

        $tenants = Tenant::query()
            ->withCount([
                'monitors',
                'users',
            ])
            ->orderBy('name')
            ->get();

        $recentChecks = MonitorCheckResult::withoutGlobalScopes()
            ->with(['monitor', 'tenant'])
            ->latest('checked_at')
            ->take(8)
            ->get();

        return view('admin.dashboard', [
            'currentUser' => $currentUser,
            'totalTenants' => $totalTenants,
            'activeTenants' => $activeTenants,
            'totalMonitors' => $totalMonitors,
            'healthyMonitors' => $healthyMonitors,
            'failingMonitors' => $failingMonitors,
            'openIncidentsCount' => $openIncidentsCount,
            'tenants' => $tenants,
            'recentChecks' => $recentChecks,
        ]);
    }
}
