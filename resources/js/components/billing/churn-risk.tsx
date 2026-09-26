import { MetricSection } from '@/components/billing/metric-section';
import type { ChurnRiskCustomer } from '@/types';

const numberFormatter = new Intl.NumberFormat('en-US');

type ChurnRiskProps = {
    customers: ChurnRiskCustomer[];
    loading?: boolean;
};

/**
 * Customers with more than a 50% month-over-month usage drop.
 */
export function ChurnRiskTable({ customers, loading = false }: ChurnRiskProps) {
    return (
        <MetricSection
            title="Churn Risk"
            description="Usage dropped more than 50% versus last month."
            loading={loading}
            isEmpty={customers.length === 0}
            emptyState="No churn-risk customers detected."
        >
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="pb-2 font-medium">Customer</th>
                        <th className="pb-2 text-right font-medium">
                            Previous month
                        </th>
                        <th className="pb-2 text-right font-medium">
                            Current month
                        </th>
                        <th className="pb-2 text-right font-medium">Drop</th>
                    </tr>
                </thead>
                <tbody>
                    {customers.map((customer) => (
                        <tr
                            key={customer.customer_id}
                            className="border-b last:border-b-0"
                        >
                            <td className="py-2">{customer.name}</td>
                            <td className="py-2 text-right tabular-nums">
                                {numberFormatter.format(
                                    customer.previous_month,
                                )}
                            </td>
                            <td className="py-2 text-right tabular-nums">
                                {numberFormatter.format(customer.current_month)}
                            </td>
                            <td className="py-2 text-right font-medium text-red-600 tabular-nums dark:text-red-400">
                                −{customer.drop_percentage.toFixed(1)}%
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </MetricSection>
    );
}
