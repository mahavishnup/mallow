/**
 * Billing dashboard metric types — mirror the payload shapes produced by
 * App\Services\DashboardService (Phase 4) and shared with the web controller.
 */

export type UsageTopCustomer = {
    customer_id: number;
    name: string;
    total_quantity: number;
};

export type ProjectedOverageSubscription = {
    subscription_id: number;
    customer_name: string;
    usage_to_date: number;
    projected_usage: number;
    included_units: number;
    projected_overage_units: number;
    projected_overage_cents: number;
};

export type ProjectedOverage = {
    total_cents: number;
    subscriptions: ProjectedOverageSubscription[];
};

export type ChurnRiskCustomer = {
    customer_id: number;
    name: string;
    previous_month: number;
    current_month: number;
    drop_percentage: number;
};
