import { MetricSection } from '@/components/billing/metric-section';
import type { UsageTopCustomer } from '@/types';

const numberFormatter = new Intl.NumberFormat('en-US');

type TopCustomersProps = {
    customers: UsageTopCustomer[];
    /** Total included units across active plans, for the % of allowance column. */
    totalIncludedUnits?: number;
    loading?: boolean;
};

/**
 * Top 5 customers by current-month usage.
 */
export function TopCustomers({
    customers,
    totalIncludedUnits = 0,
    loading = false,
}: TopCustomersProps) {
    return (
        <MetricSection
            title="Top Customers"
            description="Highest usage this billing month."
            loading={loading}
            isEmpty={customers.length === 0}
            emptyState="No usage recorded this month."
        >
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="pb-2 font-medium">#</th>
                        <th className="pb-2 font-medium">Customer</th>
                        <th className="pb-2 text-right font-medium">Usage</th>
                        <th className="pb-2 text-right font-medium">
                            % of Allowance
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {customers.map((customer, index) => (
                        <tr
                            key={customer.customer_id}
                            className="border-b last:border-b-0"
                        >
                            <td className="py-2 text-muted-foreground tabular-nums">
                                {index + 1}
                            </td>
                            <td className="py-2">{customer.name}</td>
                            <td className="py-2 text-right tabular-nums">
                                {numberFormatter.format(
                                    customer.total_quantity,
                                )}
                            </td>
                            <td className="py-2 text-right text-muted-foreground tabular-nums">
                                {totalIncludedUnits > 0
                                    ? `${((customer.total_quantity / totalIncludedUnits) * 100).toFixed(1)}%`
                                    : '—'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </MetricSection>
    );
}
