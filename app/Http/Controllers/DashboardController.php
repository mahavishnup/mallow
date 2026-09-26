<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    /**
     * Render the team dashboard with usage-insight metrics (D5.1: metrics
     * arrive as Inertia props — no client fetch).
     */
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $email = mb_strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code'        => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team'        => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $team = $request->user()->currentTeam;

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'billingMonth'       => now()->utc()->format('Y-m'),
            'usageTopCustomers'  => $team !== null
                ? $dashboard->topCustomers($team)
                : [],
            'projectedOverage' => $team !== null
                ? $dashboard->projectedOverageRevenue($team)
                : ['total_cents' => 0, 'subscriptions' => []],
            'churnRiskCustomers' => $team !== null
                ? $dashboard->churnRiskCustomers($team)
                : [],
        ]);
    }
}
