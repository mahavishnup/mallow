<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Billing\GenerateInvoiceAction;
use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $subscriptionId,
        public readonly ?string $periodStart = null,
        public readonly ?string $periodEnd = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GenerateInvoiceAction $action): void
    {
        $subscription = Subscription::query()->findOrFail($this->subscriptionId);

        $action->handle($subscription, [
            'period_start' => $this->periodStart,
            'period_end'   => $this->periodEnd,
        ]);
    }
}
