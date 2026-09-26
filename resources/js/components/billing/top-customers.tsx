import { MetricSection } from '@/components/billing/metric-section';
import type { UsageTopCustomer } from '@/types';

const numberFormatter = new Intl.NumberFormat('en-US');

type TopCustomersProps = {
    customers: UsageTopCustomer[];
    loading?: boolean;
};

/**
 * Top 5 customers by current-month usage.
 */
export function TopCustomers({
    customers,
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
                        </tr>
                    ))}
                </tbody>
            </table>
        </MetricSection>
    );
}
