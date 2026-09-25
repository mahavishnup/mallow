<?php

declare(strict_types=1);

use App\Actions\Billing\ChangeSubscriptionPlanAction;
use App\Actions\Billing\CreateSubscriptionAction;
use App\Actions\Billing\GenerateInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Team;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->merchant = Team::factory()->create();
    $this->customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);

    // Clean numbers: $30.00/mo, 1,000 units included, $0.01/unit overage, 30-day cycle.
    $this->plan = Plan::factory()->create([
        'merchant_id'    => $this->merchant->id,
        'base_price'     => '30.00',
        'included_units' => 1_000,
        'overage_rate'   => '0.0100',
    ]);

    $this->createAction = app(CreateSubscriptionAction::class);
    $this->changeAction = app(ChangeSubscriptionPlanAction::class);
    $this->invoiceAction = app(GenerateInvoiceAction::class);
});

/**
 * Seed daily_usage totals for a date range (half-open [start, end)).
 */
function seedUsage(int $customerId, string $start, string $end, int $dailyQuantity): void
{
    $cursor = Carbon::parse($start);

    while ($cursor->lessThan(Carbon::parse($end))) {
        DailyUsage::factory()->forDay($customerId, $cursor->toDateString(), $dailyQuantity, 1)->create();
        $cursor->addDay();
    }
}

test('usage within allowance bills base only', function (): void {
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-01',
    ]);

    seedUsage($this->customer->id, '2026-09-01', '2026-10-01', 20); // 600 total < 1000

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    expect($invoice->total_amount)->toBe('30.00')
        ->and($invoice->items()->sole()->overage_units)->toBe(0)
        ->and($invoice->items()->sole()->overage_amount)->toBe('0.00')
        ->and($invoice->items()->sole()->line_total)->toBe('30.00');
});

test('usage exactly at allowance yields zero overage', function (): void {
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-01',
    ]);

    // 29 days × 33 = 957, plus Sep 30 with 43 → exactly 1,000.
    seedUsage($this->customer->id, '2026-09-01', '2026-09-30', 33);
    DailyUsage::factory()->forDay($this->customer->id, '2026-09-30', 43, 1)->create();

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    expect($invoice->items()->sole()->billable_usage)->toBe(1_000)
        ->and($invoice->items()->sole()->overage_units)->toBe(0)
        ->and($invoice->total_amount)->toBe('30.00');
});

test('usage above allowance computes correct overage units and amount', function (): void {
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-01',
    ]);

    seedUsage($this->customer->id, '2026-09-01', '2026-10-01', 50); // 1500 total

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    $item = $invoice->items()->sole();

    expect($item->billable_usage)->toBe(1_500)
        ->and($item->overage_units)->toBe(500)
        ->and($item->overage_amount)->toBe('5.00')   // 500 × $0.01
        ->and($item->line_total)->toBe('35.00')       // 30 + 5
        ->and($invoice->total_amount)->toBe('35.00');
});

test('zero usage still bills the base (D3.4)', function (): void {
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-01',
    ]);

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    $item = $invoice->items()->sole();

    expect($item->billable_usage)->toBe(0)
        ->and($item->prorated_base)->toBe('30.00')
        ->and($invoice->total_amount)->toBe('30.00');
});

test('mid-cycle subscription start prorates the base', function (): void {
    // Start Sep 16 → 15 active days of a 30-day cycle.
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-16',
    ]);

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    $item = $invoice->items()->sole();

    // $30 × 15/30 = $15.00; allowance 1000 × 15/30 = 500.
    expect($item->prorated_base)->toBe('15.00')
        ->and($item->included_units)->toBe(500)
        ->and($invoice->total_amount)->toBe('15.00');
});

test('multiple segments produce per-segment totals', function (): void {
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-01',
    ]);

    seedUsage($this->customer->id, '2026-09-01', '2026-09-16', 30);  // 450 in seg 1
    seedUsage($this->customer->id, '2026-09-16', '2026-10-01', 80);  // 1200 in seg 2

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    expect($invoice->items()->count())->toBe(1)
        ->and($invoice->items()->sole()->billable_usage)->toBe(1_650);
});

test('upgrade mid-cycle bills old usage at the old rate and new usage at the new rate', function (): void {
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-01',
    ]);

    // Upgrade effective Sep 16 to: $60/mo, 2,000 units, $0.02/unit.
    $newPlan = Plan::factory()->create(['merchant_id' => $this->merchant->id, 'base_price' => '60.00', 'included_units' => 2_000, 'overage_rate' => '0.0200']);
    $this->changeAction->handle($subscription, ['plan_id' => $newPlan->id, 'effective_date' => '2026-09-16']);

    seedUsage($this->customer->id, '2026-09-01', '2026-09-16', 50);  // 750 units in seg 1
    seedUsage($this->customer->id, '2026-09-16', '2026-10-01', 100); // 1,500 units in seg 2

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    $items = $invoice->items()->orderBy('segment_start')->get();

    // Segment 1: old plan, 15 days → base $15, allowance 500, usage 750 → 250 over × $0.01 = $2.50.
    expect($items[0]->plan_id)->toBe($this->plan->id)
        ->and($items[0]->prorated_base)->toBe('15.00')
        ->and($items[0]->overage_units)->toBe(250)
        ->and($items[0]->overage_amount)->toBe('2.50')
        ->and($items[0]->line_total)->toBe('17.50');

    // Segment 2: new plan, 15 days → base $30, allowance 1000, usage 1500 → 500 over × $0.02 = $10.00.
    expect($items[1]->plan_id)->toBe($newPlan->id)
        ->and($items[1]->prorated_base)->toBe('30.00')
        ->and($items[1]->overage_units)->toBe(500)
        ->and($items[1]->overage_amount)->toBe('10.00')
        ->and($items[1]->line_total)->toBe('40.00');

    expect($invoice->total_amount)->toBe('57.50'); // Σ segments
});

test('downgrade mid-cycle prorates both allowances', function (): void {
    $expensive = Plan::factory()->create(['merchant_id' => $this->merchant->id, 'base_price' => '100.00', 'included_units' => 10_000, 'overage_rate' => '0.0500']);

    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $expensive->id,
        'starts_at'   => '2026-09-01',
    ]);

    // Downgrade effective Sep 21 to the basic plan (10 days into the new segment).
    $this->changeAction->handle($subscription, ['plan_id' => $this->plan->id, 'effective_date' => '2026-09-21']);

    seedUsage($this->customer->id, '2026-09-01', '2026-09-21', 300); // 6,000 units in seg 1
    seedUsage($this->customer->id, '2026-09-21', '2026-10-01', 80);  // 800 units in seg 2

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    $items = $invoice->items()->orderBy('segment_start')->get();

    // Segment 1: expensive, 20 days → base $66.67 (6666.67 → 6667 cents), allowance 6,667.
    expect($items[0]->prorated_base)->toBe('66.67')
        ->and($items[0]->included_units)->toBe(6_667)
        ->and($items[0]->billable_usage)->toBe(6_000)
        ->and($items[0]->overage_units)->toBe(0);

    // Segment 2: basic, 10 days → base $10, allowance 333.33 → 333.
    expect($items[1]->prorated_base)->toBe('10.00')
        ->and($items[1]->included_units)->toBe(333)
        ->and($items[1]->billable_usage)->toBe(800)
        ->and($items[1]->overage_units)->toBe(467)   // 800 − 333
        ->and($items[1]->overage_amount)->toBe('4.67'); // 467 × $0.01 = 4.67

    // 66.67 + 10.00 + 4.67 = 81.34
    expect($invoice->total_amount)->toBe('81.34');
});

test('invoice total equals the sum of segment line totals', function (): void {
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-01',
    ]);

    $newPlan = Plan::factory()->create(['merchant_id' => $this->merchant->id, 'base_price' => '60.00', 'included_units' => 2_000, 'overage_rate' => '0.0200']);
    $this->changeAction->handle($subscription, ['plan_id' => $newPlan->id, 'effective_date' => '2026-09-16']);

    seedUsage($this->customer->id, '2026-09-01', '2026-10-01', 100); // 3000 total

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    $sum = $invoice->items()->get()->sum(fn ($item) => (float) $item->line_total);

    expect((float) $invoice->total_amount)->toBe($sum);
});

test('generating an invoice twice for the same period is idempotent', function (): void {
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-01',
    ]);

    seedUsage($this->customer->id, '2026-09-01', '2026-10-01', 50);

    $first = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    // New usage aggregated after the first build → draft is rebuilt, still one invoice.
    DailyUsage::query()
        ->where('customer_id', $this->customer->id)
        ->whereDate('usage_date', '2026-09-05')
        ->update(['total_quantity' => 150]);

    $second = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    expect($second->id)->toBe($first->id)
        ->and(Invoice::query()->count())->toBe(1)
        ->and($second->items()->count())->toBe(1);
});

test('finalized invoices are never rebuilt', function (): void {
    $subscription = $this->createAction->handle($this->merchant->id, [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => '2026-09-01',
    ]);

    seedUsage($this->customer->id, '2026-09-01', '2026-10-01', 50);

    $invoice = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    $invoice->update(['status' => InvoiceStatus::Finalized->value]);

    // More usage arrives — must NOT change the finalized invoice.
    DailyUsage::query()
        ->where('customer_id', $this->customer->id)
        ->whereDate('usage_date', '2026-09-02')
        ->update(['total_quantity' => 1_049]);

    $rebuilt = $this->invoiceAction->handle($subscription, [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ]);

    expect($rebuilt->id)->toBe($invoice->id)
        ->and($rebuilt->status)->toBe(InvoiceStatus::Finalized)
        ->and($rebuilt->total_amount)->toBe($invoice->total_amount);
});
