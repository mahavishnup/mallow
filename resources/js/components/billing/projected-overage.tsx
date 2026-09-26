import { MetricSection } from '@/components/billing/metric-section';
import type { ProjectedOverage } from '@/types';

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const numberFormatter = new Intl.NumberFormat('en-US');

type ProjectedOverageCardProps = {
    projectedOverage: ProjectedOverage;
    loading?: boolean;
};

/**
 * Projected overage revenue at cycle end (linear projection — estimate only).
 */
export function ProjectedOverageCard({
    projectedOverage,
    loading = false,
}: ProjectedOverageCardProps) {
    return (
        <MetricSection
            title="Projected Overage Revenue"
            description="Linear projection to each cycle's end — estimate, not billed."
            loading={loading}
            isEmpty={projectedOverage.subscriptions.length === 0}
            emptyState="No active subscriptions."
        >
            <p
                className="text-2xl font-semibold tabular-nums"
                data-testid="overage-total"
            >
                {currencyFormatter.format(projectedOverage.total_cents / 100)}
            </p>
            <table className="mt-4 w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="pb-2 font-medium">Customer</th>
                        <th className="pb-2 text-right font-medium">
                            Usage to date
                        </th>
                        <th className="pb-2 text-right font-medium">
                            Projected
                        </th>
                        <th className="pb-2 text-right font-medium">
                            Included
                        </th>
                        <th className="pb-2 text-right font-medium">Overage</th>
                    </tr>
                </thead>
                <tbody>
                    {projectedOverage.subscriptions.map((subscription) => (
                        <tr
                            key={subscription.subscription_id}
                            className="border-b last:border-b-0"
                        >
                            <td className="py-2">
                                {subscription.customer_name}
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {numberFormatter.format(
                                    subscription.usage_to_date,
                                )}
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {numberFormatter.format(
                                    subscription.projected_usage,
                                )}
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {numberFormatter.format(
                                    subscription.included_units,
                                )}
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {numberFormatter.format(
                                    subscription.projected_overage_units,
                                )}{' '}
                                (
                                {currencyFormatter.format(
                                    subscription.projected_overage_cents / 100,
                                )}
                                )
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </MetricSection>
    );
}
