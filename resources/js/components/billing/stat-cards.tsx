import { Activity, CreditCard, TrendingUp } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    ActivePlanSummary,
    CycleOverview,
    ProjectedOverage,
} from '@/types';

const numberFormatter = new Intl.NumberFormat('en-US');
const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

type StatCardsProps = {
    cycleOverview: CycleOverview;
    projectedOverage: ProjectedOverage;
    activePlan: ActivePlanSummary | null;
    loading?: boolean;
};

function UsageBar({ percentage }: { percentage: number }) {
    const clamped = Math.min(100, Math.max(0, percentage));

    return (
        <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-muted">
            <div
                className="h-full rounded-full bg-primary transition-all"
                style={{ width: `${clamped}%` }}
                role="progressbar"
                aria-valuenow={Math.round(clamped)}
                aria-valuemin={0}
                aria-valuemax={100}
            />
        </div>
    );
}

/**
 * Top stat row: Current Cycle Usage, Projected Overage Revenue, Active Plan —
 * mirrors the wireframe's three colour-barred metric cards.
 */
export function StatCards({
    cycleOverview,
    projectedOverage,
    activePlan,
    loading = false,
}: StatCardsProps) {
    if (loading) {
        return (
            <div
                className="grid gap-4 sm:grid-cols-3"
                data-testid="stat-cards-loading"
            >
                <Skeleton className="h-28" />
                <Skeleton className="h-28" />
                <Skeleton className="h-28" />
            </div>
        );
    }

    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <Card className="gap-2 border-l-4 border-l-blue-500 py-4">
                <CardHeader className="px-4">
                    <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Activity className="size-4" />
                        Current Cycle Usage
                    </CardTitle>
                </CardHeader>
                <CardContent className="px-4">
                    <p
                        className="text-xl font-semibold tabular-nums"
                        data-testid="stat-cycle-usage"
                    >
                        {numberFormatter.format(cycleOverview.usage_to_date)}
                        <span className="text-sm font-normal text-muted-foreground">
                            {' '}
                            /{' '}
                            {numberFormatter.format(
                                cycleOverview.included_units,
                            )}{' '}
                            units
                        </span>
                    </p>
                    <UsageBar percentage={cycleOverview.usage_percentage} />
                </CardContent>
            </Card>

            <Card className="gap-2 border-l-4 border-l-amber-500 py-4">
                <CardHeader className="px-4">
                    <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <TrendingUp className="size-4" />
                        Projected Overage Revenue
                    </CardTitle>
                </CardHeader>
                <CardContent className="px-4">
                    <p
                        className="text-xl font-semibold tabular-nums"
                        data-testid="stat-projected-overage"
                    >
                        {currencyFormatter.format(
                            projectedOverage.total_cents / 100,
                        )}
                    </p>
                    <p className="mt-3 text-xs text-muted-foreground">
                        Linear projection to cycle end — estimate, not billed.
                    </p>
                </CardContent>
            </Card>

            <Card className="gap-2 border-l-4 border-l-emerald-500 py-4">
                <CardHeader className="px-4">
                    <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <CreditCard className="size-4" />
                        Active Plan
                    </CardTitle>
                </CardHeader>
                <CardContent className="px-4">
                    {activePlan ? (
                        <>
                            <p
                                className="text-xl font-semibold"
                                data-testid="stat-active-plan"
                            >
                                {activePlan.name}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Monthly · {activePlan.billing_cycle_days}-day
                                cycle
                            </p>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No active subscriptions.
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
