import { Head } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { useState } from 'react';
import { ChurnRiskTable } from '@/components/billing/churn-risk';
import { StatCards } from '@/components/billing/stat-cards';
import { SystemStatusPanel } from '@/components/billing/system-status';
import { TopCustomers } from '@/components/billing/top-customers';
import { UsageTrendChart } from '@/components/billing/usage-trend-chart';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { dashboard } from '@/routes';
import type {
    ActivePlanSummary,
    ChurnRiskCustomer,
    CycleOverview,
    DailyUsageTrendPoint,
    DashboardInvitation,
    ProjectedOverage,
    UsageTopCustomer,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    billingMonth?: string;
    cycleOverview?: CycleOverview;
    activePlan?: ActivePlanSummary | null;
    dailyTrend?: DailyUsageTrendPoint[];
    usageTopCustomers?: UsageTopCustomer[];
    projectedOverage?: ProjectedOverage;
    churnRiskCustomers?: ChurnRiskCustomer[];
};

export default function Dashboard({
    pendingInvitations = [],
    billingMonth,
    cycleOverview = {
        usage_to_date: 0,
        included_units: 0,
        usage_percentage: 0,
    },
    activePlan = null,
    dailyTrend = [],
    usageTopCustomers = [],
    projectedOverage = { total_cents: 0, subscriptions: [] },
    churnRiskCustomers = [],
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold">
                        Merchant Dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {billingMonth
                            ? `Billing month ${billingMonth}`
                            : 'Billing overview'}
                    </p>
                </div>
                <StatCards
                    cycleOverview={cycleOverview}
                    projectedOverage={projectedOverage}
                    activePlan={activePlan}
                />
                <div className="grid gap-4 lg:grid-cols-2">
                    <TopCustomers
                        customers={usageTopCustomers}
                        totalIncludedUnits={cycleOverview.included_units}
                    />
                    <div className="flex flex-col gap-4">
                        <ChurnRiskTable customers={churnRiskCustomers} />
                        <SystemStatusPanel churnThreshold={0.5} />
                    </div>
                </div>
                <UsageTrendChart trend={dailyTrend} />
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <BookOpen className="size-3.5" />
                    <span>
                        API reference lives at{' '}
                        <a
                            href="/docs"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="underline decoration-dotted underline-offset-2 hover:text-foreground"
                        >
                            /docs
                        </a>{' '}
                        — usage ingestion, billing, and dashboard endpoints.
                    </span>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
