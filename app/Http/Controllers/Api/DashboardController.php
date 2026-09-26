<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveApiKey;
use App\Models\Team;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    /**
     * Merchant dashboard metrics (Plan §5.2).
     *
     * Dual auth (D4.2): X-Api-Key resolves the merchant directly; otherwise a
     * session-authenticated user must belong to the target team.
     */
    public function __invoke(Request $request, DashboardService $dashboard, int $id): JsonResponse
    {
        $merchant = $this->resolveMerchant($request, $id);

        return response()->json([
            'data' => [
                'merchant' => [
                    'id'   => $merchant->id,
                    'name' => $merchant->name,
                ],
                'month'             => now()->utc()->format('Y-m'),
                'cycle_overview'    => $dashboard->cycleOverview($merchant),
                'active_plan'       => $dashboard->activePlanSummary($merchant),
                'daily_trend'       => $dashboard->dailyUsageTrend($merchant),
                'top_customers'     => $dashboard->topCustomers($merchant),
                'projected_overage' => $dashboard->projectedOverageRevenue($merchant),
                'churn_risk'        => $dashboard->churnRiskCustomers($merchant),
            ],
        ]);
    }

    /**
     * Resolve the merchant from the API key, falling back to team membership.
     */
    private function resolveMerchant(Request $request, int $id): Team
    {
        $keyMerchant = $request->attributes->get(ResolveApiKey::ATTRIBUTE_MERCHANT);

        if ($keyMerchant !== null) {
            if ($keyMerchant->id !== $id) {
                abort(403, 'api_key_merchant_mismatch');
            }

            return $keyMerchant;
        }

        $user = $request->user();

        if ($user === null) {
            abort(401, 'unauthenticated');
        }

        /** @var Team|null $team */
        $team = Team::query()->find($id);

        if ($team === null || ! $user->belongsToTeam($team)) {
            abort(403, 'not_a_team_member');
        }

        return $team;
    }
}
