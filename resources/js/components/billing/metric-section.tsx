import type { ReactNode } from 'react';

import { router } from '@inertiajs/react';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type MetricSectionProps = {
    title: string;
    description?: string;
    loading?: boolean;
    error?: string | null;
    isEmpty: boolean;
    emptyState: ReactNode;
    children: ReactNode;
    className?: string;
};

/**
 * Shared wrapper for dashboard metric sections: consistent card styling with
 * loading skeleton, empty slot, and an error state offering a reload.
 */
export function MetricSection({
    title,
    description,
    loading = false,
    error = null,
    isEmpty,
    emptyState,
    children,
    className,
}: MetricSectionProps) {
    return (
        <Card className={cn('gap-4', className)}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent>
                {loading ? (
                    <div className="space-y-2" data-testid="metric-loading">
                        <Skeleton className="h-8 w-full" />
                        <Skeleton className="h-8 w-full" />
                        <Skeleton className="h-8 w-3/4" />
                    </div>
                ) : error ? (
                    <div
                        className="flex flex-col items-start gap-2 text-sm text-destructive"
                        data-testid="metric-error"
                    >
                        <span>{error}</span>
                        <button
                            type="button"
                            className="text-foreground underline underline-offset-4"
                            onClick={() => router.reload({ only: [] })}
                        >
                            Retry
                        </button>
                    </div>
                ) : isEmpty ? (
                    <p
                        className="text-sm text-muted-foreground"
                        data-testid="metric-empty"
                    >
                        {emptyState}
                    </p>
                ) : (
                    children
                )}
            </CardContent>
        </Card>
    );
}
