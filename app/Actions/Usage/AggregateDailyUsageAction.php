<?php

declare(strict_types=1);

namespace App\Actions\Usage;

use App\Models\UsageEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AggregateDailyUsageAction
{
    /**
     * Aggregate unprocessed usage events into daily_usage (Plan §6).
     *
     * Chunked by primary-key cursor with a same-transaction watermark
     * (aggregated_at): a crashed or re-run job only ever processes rows whose
     * watermark has not advanced, so totals are never double-counted.
     *
     * @param  int|null  $customerId  Optional scope: aggregate only this customer.
     * @param  string|null  $usageDate  Optional scope: aggregate only this date (Y-m-d).
     */
    public function execute(?int $customerId = null, ?string $usageDate = null): void
    {
        $this->baseQuery($customerId, $usageDate)
            ->orderBy('id')
            ->chunkById(
                (int) config('billing.aggregation.chunk_size'),
                fn (Collection $chunk): bool => $this->processChunk($chunk),
            );
    }

    /**
     * The unprocessed-events query, optionally scoped for targeted dispatch.
     *
     * @return \Illuminate\Database\Eloquent\Builder<UsageEvent>
     */
    private function baseQuery(?int $customerId, ?string $usageDate): \Illuminate\Database\Eloquent\Builder
    {
        $query = UsageEvent::query()->unaggregated();

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        if ($usageDate !== null) {
            $query->whereDate('usage_date', $usageDate);
        }

        return $query;
    }

    /**
     * Upsert one chunk's deltas and advance the watermark atomically.
     *
     * @param  Collection<int, UsageEvent>  $chunk
     */
    private function processChunk(Collection $chunk): bool
    {
        if ($chunk->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($chunk): void {
            $deltas = array_values($chunk
                ->groupBy(fn (UsageEvent $event): string => $event->customer_id . '|' . $event->usage_date->toDateString())
                ->map(fn (Collection $group): array => [
                    'merchant_id'    => $group->first()->merchant_id,
                    'customer_id'    => $group->first()->customer_id,
                    'usage_date'     => $group->first()->usage_date->toDateString(),
                    'total_quantity' => (int) $group->sum('quantity'),
                    'event_count'    => $group->count(),
                ])
                ->all());

            $this->upsertDeltas($deltas);

            // Advance the watermark for exactly this chunk's rows, guarded so
            // a concurrent worker cannot re-mark (and thus re-count) them.
            UsageEvent::query()
                ->whereIn('id', $chunk->pluck('id'))
                ->whereNull('aggregated_at')
                ->update(['aggregated_at' => now()]);
        });

        return true;
    }

    /**
     * Insert-or-increment daily_usage rows in a single statement.
     *
     * Compiles to INSERT … ON CONFLICT (customer_id, usage_date) DO UPDATE
     * SET total_quantity = daily_usage.total_quantity + excluded.total_quantity
     * on both PostgreSQL and SQLite — the row-level atomicity Plan §6 requires
     * under duplicate dispatch.
     *
     * @param  list<array{merchant_id: int, customer_id: int, usage_date: string, total_quantity: int, event_count: int}>  $deltas
     */
    private function upsertDeltas(array $deltas): void
    {
        $now = now();

        $rows = array_map(static fn (array $delta): array => [
            ...$delta,
            'created_at' => $now,
            'updated_at' => $now,
        ], $deltas);

        DB::table('daily_usage')->upsert(
            $rows,
            ['customer_id', 'usage_date'],
            [
                // Numeric-keyed entries compile to `column = excluded.column`.
                'updated_at',
                // String-keyed raw expressions increment atomically in-place.
                'total_quantity' => DB::raw('daily_usage.total_quantity + excluded.total_quantity'),
                'event_count'    => DB::raw('daily_usage.event_count + excluded.event_count'),
            ],
        );
    }
}
