import { Info } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type StatusRow = {
    label: string;
    value: string;
};

type SystemStatusProps = {
    churnThreshold: number;
};

/**
 * Informational panel documenting the platform limits and thresholds behind
 * the dashboard numbers (mirrors the wireframe's "System status" box).
 */
export function SystemStatusPanel({ churnThreshold }: SystemStatusProps) {
    const rows: StatusRow[] = [
        { label: 'Plan pricing cache', value: 'TTL 10m' },
        { label: 'Monthly aggregation', value: 'queued' },
        { label: 'Churned link', value: 'raw/chart' },
        { label: 'Usage limit', value: 'rate limited' },
        { label: 'API key limit', value: '600 req/min' },
    ];

    return (
        <Card className="gap-2 border-blue-500/40 bg-blue-500/5 py-4">
            <CardHeader className="px-4">
                <CardTitle className="flex items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-400">
                    <Info className="size-4" />
                    System status (informational)
                </CardTitle>
            </CardHeader>
            <CardContent className="px-4">
                <dl className="space-y-1.5 text-xs">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className="flex items-center justify-between gap-2"
                        >
                            <dt className="text-muted-foreground">
                                {row.label}
                            </dt>
                            <dd className="font-medium">{row.value}</dd>
                        </div>
                    ))}
                    <div className="flex items-center justify-between gap-2">
                        <dt className="text-muted-foreground">
                            Churn risk threshold
                        </dt>
                        <dd className="font-medium">
                            drop &gt; {Math.round(churnThreshold * 100)}% MoM
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}
