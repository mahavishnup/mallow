import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ChurnRiskTable } from '@/components/billing/churn-risk';
import { ProjectedOverageCard } from '@/components/billing/projected-overage';
import { TopCustomers } from '@/components/billing/top-customers';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { dashboard } from '@/routes';
import type {
    ChurnRiskCustomer,
    DashboardInvitation,
    ProjectedOverage,
    UsageTopCustomer,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    billingMonth?: string;
    usageTopCustomers?: UsageTopCustomer[];
    projectedOverage?: ProjectedOverage;
    churnRiskCustomers?: ChurnRiskCustomer[];
};

export default function Dashboard({
    pendingInvitations = [],
    billingMonth,
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
                    <h1 className="text-2xl font-semibold">Usage Insights</h1>
                    <p className="text-sm text-muted-foreground">
                        {billingMonth
                            ? `Billing month ${billingMonth}`
                            : 'Billing overview'}
                    </p>
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    <TopCustomers customers={usageTopCustomers} />
                    <ProjectedOverageCard projectedOverage={projectedOverage} />
                </div>
                <ChurnRiskTable customers={churnRiskCustomers} />
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
