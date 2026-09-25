<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Http\Requests\Api\RecordUsageRequest;
use App\Jobs\AggregateDailyUsageJob;
use App\Models\Customer;
use App\Models\Team;
use App\Models\UsageEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RecordUsageAction
{
    /**
     * Record a usage event for a merchant's customer.
     *
     * Idempotency is enforced by the unique `uuid` constraint at the database
     * level; the pre-insert existence check is only a fast path.
     *
     * @return array{stored: false}|array{stored: true, event: UsageEvent}
     *
     * @throws ValidationException When the customer is not owned by the merchant
     *                             or has no active subscription covering the usage date.
     */
    public function handle(Team $merchant, RecordUsageRequest $request): array
    {
        /** @var Customer $customer */
        $customer = Customer::query()
            ->where('merchant_id', $merchant->id)
            ->findOrFail($request->integer('customer_id'), ['id', 'merchant_id']);

        $usageDate = Carbon::parse($request->string('usage_date')->toString());

        $this->assertActiveSubscription($customer, $usageDate);

        $idempotencyKey = $request->string('idempotency_key')->toString();

        // Fast path: skip the write attempt when the idempotency key is known.
        $duplicate = UsageEvent::query()
            ->where('uuid', $idempotencyKey)
            ->exists();

        if ($duplicate) {
            return ['stored' => false];
        }

        try {
            $event = UsageEvent::query()->create([
                'uuid'        => $idempotencyKey,
                'merchant_id' => $customer->merchant_id,
                'customer_id' => $customer->id,
                'usage_date'  => $usageDate->toDateString(),
                'quantity'    => $request->integer('quantity'),
                'type'        => $request->string('type')->toString(),
            ]);
        } catch (QueryException $exception) {
            // The unique constraint on `uuid` is the real idempotency guard:
            // a concurrent duplicate loses the race and lands here.
            if ($this->isUniqueViolation($exception)) {
                return ['stored' => false];
            }

            throw $exception;
        }

        // Aggregate asynchronously — never in the request path (Plan §5.1).
        // afterCommit keeps the job from racing the insert's transaction.
        AggregateDailyUsageJob::dispatch($customer->id, $usageDate->toDateString())->afterCommit();

        return ['stored' => true, 'event' => $event];
    }

    /**
     * Assert the customer has an active subscription covering the usage date (D1.2).
     *
     * @throws ValidationException
     */
    private function assertActiveSubscription(Customer $customer, Carbon $usageDate): void
    {
        $covered = $customer->subscriptions()
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', $usageDate)
            ->where(function ($query) use ($usageDate): void {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>', $usageDate);
            })
            ->exists();

        if (! $covered) {
            throw ValidationException::withMessages([
                'customer_id' => 'no_active_subscription',
            ]);
        }
    }

    /**
     * Detect a unique-constraint violation across PostgreSQL (23505) and SQLite.
     */
    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = $exception->getCode();

        return $code === '23505' || $code === '23000';
    }
}
