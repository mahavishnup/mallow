import { useMemo } from 'react';

import { MetricSection } from '@/components/billing/metric-section';
import type { DailyUsageTrendPoint } from '@/types';

const WIDTH = 640;
const HEIGHT = 180;
const PADDING = { top: 12, right: 8, bottom: 24, left: 48 };

type UsageTrendChartProps = {
    trend: DailyUsageTrendPoint[];
    loading?: boolean;
};

/**
 * Daily usage trend for the last 30 days as a dependency-free inline SVG
 * area/line chart (zero-filled gaps from the service layer).
 */
export function UsageTrendChart({
    trend,
    loading = false,
}: UsageTrendChartProps) {
    const { linePath, areaPath, max, points, ticks } = useMemo(() => {
        const innerWidth = WIDTH - PADDING.left - PADDING.right;
        const innerHeight = HEIGHT - PADDING.top - PADDING.bottom;
        const maxValue = Math.max(
            1,
            ...trend.map((point) => point.total_quantity),
        );

        const toX = (index: number): number =>
            PADDING.left +
            (trend.length <= 1
                ? innerWidth / 2
                : (index / (trend.length - 1)) * innerWidth);
        const toY = (value: number): number =>
            PADDING.top + innerHeight - (value / maxValue) * innerHeight;

        const coords = trend.map((point, index) => ({
            x: toX(index),
            y: toY(point.total_quantity),
            point,
        }));

        const line = coords
            .map(
                (coord, index) =>
                    `${index === 0 ? 'M' : 'L'}${coord.x.toFixed(1)},${coord.y.toFixed(1)}`,
            )
            .join(' ');

        const baseline = PADDING.top + innerHeight;
        const area =
            coords.length > 0
                ? `${line} L${coords[coords.length - 1].x.toFixed(1)},${baseline} L${coords[0].x.toFixed(1)},${baseline} Z`
                : '';

        const yTicks = [0, 0.5, 1].map((fraction) => ({
            value: Math.round(maxValue * fraction),
            y: toY(maxValue * fraction),
        }));

        const xTicks = [0, Math.floor((trend.length - 1) / 2), trend.length - 1]
            .filter(
                (index, position, all) =>
                    index >= 0 && all.indexOf(index) === position,
            )
            .map((index) => ({
                label: trend[index]?.date.slice(5) ?? '',
                x: toX(index),
            }));

        return {
            linePath: line,
            areaPath: area,
            max: maxValue,
            points: coords,
            ticks: { y: yTicks, x: xTicks },
        };
    }, [trend]);

    return (
        <MetricSection
            title="Daily Usage Trend"
            description="Total usage per day, last 30 days (UTC)."
            loading={loading}
            isEmpty={trend.length === 0}
            emptyState="No usage recorded yet."
        >
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="h-48 w-full"
                role="img"
                aria-label={`Daily usage trend, peak ${max} units`}
                data-testid="usage-trend-chart"
            >
                {ticks.y.map((tick) => (
                    <g key={`y-${tick.value}`}>
                        <line
                            x1={PADDING.left}
                            x2={WIDTH - PADDING.right}
                            y1={tick.y}
                            y2={tick.y}
                            className="stroke-border"
                            strokeDasharray="3 3"
                        />
                        <text
                            x={PADDING.left - 6}
                            y={tick.y + 3}
                            textAnchor="end"
                            className="fill-muted-foreground text-[10px]"
                        >
                            {tick.value}
                        </text>
                    </g>
                ))}

                <path d={areaPath} className="fill-primary/15" />
                <path
                    d={linePath}
                    fill="none"
                    className="stroke-primary"
                    strokeWidth={2}
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />

                {points.map((coord) => (
                    <circle
                        key={coord.point.date}
                        cx={coord.x}
                        cy={coord.y}
                        r={2.5}
                        className="fill-primary"
                    >
                        <title>{`${coord.point.date}: ${coord.point.total_quantity}`}</title>
                    </circle>
                ))}

                {ticks.x.map((tick) => (
                    <text
                        key={`x-${tick.label}`}
                        x={tick.x}
                        y={HEIGHT - 6}
                        textAnchor="middle"
                        className="fill-muted-foreground text-[10px]"
                    >
                        {tick.label}
                    </text>
                ))}
            </svg>
        </MetricSection>
    );
}
