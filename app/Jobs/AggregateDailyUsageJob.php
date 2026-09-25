<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Usage\AggregateDailyUsageAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class AggregateDailyUsageJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $customerId  Scope to one customer (ingestion dispatch), or null for a full sweep.
     * @param  string|null  $usageDate  Scope to one date (Y-m-d), or null for all dates.
     */
    public function __construct(
        public readonly ?int $customerId = null,
        public readonly ?string $usageDate = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AggregateDailyUsageAction $action): void
    {
        $action->execute($this->customerId, $this->usageDate);
    }
}
