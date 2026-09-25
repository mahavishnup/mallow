<?php

declare(strict_types=1);

use App\Jobs\AggregateDailyUsageJob;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

// Safety-net sweep: catches any events a failed/scoped job left un-aggregated
// (Plan §6 retry story). Scopes to null → full watermark-driven pass.
Schedule::job(new AggregateDailyUsageJob)
    ->daily()
    ->at('02:00')
    ->description('Aggregate any un-processed usage events');
