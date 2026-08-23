<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\IdempotencyKey;
use App\Services\IdempotencyService;
use App\Support\IdempotencyConflictException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises IdempotencyService directly, with a trivial counting callback
 * standing in for "the real operation" — Sales doesn't exist yet, and this
 * mechanism must be provably correct independent of any one consumer.
 */
class IdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): IdempotencyService
    {
        return app(IdempotencyService::class);
    }

    private int $executions = 0;

    private function countingCallback(): \Closure
    {
        return function () {
            $this->executions++;

            return [201, ['created' => $this->executions]];
        };
    }

    public function test_first_request_executes_the_operation_exactly_once(): void
    {
        $business = Business::factory()->create();

        $result = $this->service()->handle($business, 'txn-1', ['amount' => 10], null, $this->countingCallback());

        $this->assertSame(1, $this->executions);
        $this->assertFalse($result['replayed']);
        $this->assertSame(201, $result['status']);
        $this->assertDatabaseHas('idempotency_keys', ['business_id' => $business->id, 'client_transaction_id' => 'txn-1']);
    }

    public function test_same_request_repeated_replays_the_original_result_without_re_executing(): void
    {
        $business = Business::factory()->create();
        $payload = ['amount' => 10];

        $first = $this->service()->handle($business, 'txn-2', $payload, null, $this->countingCallback());
        $second = $this->service()->handle($business, 'txn-2', $payload, null, $this->countingCallback());

        $this->assertSame(1, $this->executions);
        $this->assertFalse($first['replayed']);
        $this->assertTrue($second['replayed']);
        $this->assertSame($first['body'], $second['body']);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_same_key_after_a_timeout_with_no_recorded_response_is_actually_executed(): void
    {
        $business = Business::factory()->create();

        // Simulates a prior attempt that crashed/timed out before it could
        // record a response — the row exists, but response_status is null.
        IdempotencyKey::query()->create([
            'business_id' => $business->id,
            'client_transaction_id' => 'txn-3',
            'request_hash' => hash('sha256', json_encode(['amount' => 10])),
        ]);

        $result = $this->service()->handle($business, 'txn-3', ['amount' => 10], null, $this->countingCallback());

        $this->assertSame(1, $this->executions);
        $this->assertFalse($result['replayed']);
        $this->assertDatabaseHas('idempotency_keys', ['business_id' => $business->id, 'client_transaction_id' => 'txn-3', 'response_status' => 201]);
    }

    public function test_same_key_with_a_different_payload_is_rejected_as_a_conflict(): void
    {
        $business = Business::factory()->create();
        $this->service()->handle($business, 'txn-4', ['amount' => 10], null, $this->countingCallback());

        $this->expectException(IdempotencyConflictException::class);
        $this->service()->handle($business, 'txn-4', ['amount' => 999], null, $this->countingCallback());
    }

    public function test_a_different_key_creates_a_separate_execution(): void
    {
        $business = Business::factory()->create();

        $this->service()->handle($business, 'txn-5a', ['amount' => 10], null, $this->countingCallback());
        $this->service()->handle($business, 'txn-5b', ['amount' => 10], null, $this->countingCallback());

        $this->assertSame(2, $this->executions);
        $this->assertDatabaseCount('idempotency_keys', 2);
    }

    public function test_the_same_key_string_used_by_two_different_businesses_never_collides(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $resultA = $this->service()->handle($businessA, 'shared-key', ['amount' => 10], null, $this->countingCallback());
        $resultB = $this->service()->handle($businessB, 'shared-key', ['amount' => 10], null, $this->countingCallback());

        $this->assertSame(2, $this->executions);
        $this->assertFalse($resultA['replayed']);
        $this->assertFalse($resultB['replayed']);
        $this->assertDatabaseCount('idempotency_keys', 2);
    }

    public function test_a_business_cannot_read_or_reuse_another_businesss_key_record(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $this->service()->handle($businessA, 'private-key', ['amount' => 10], null, $this->countingCallback());

        // Business B submitting the identical key string is treated as a
        // brand-new key for B — it is not blocked by, nor able to replay,
        // A's completed transaction.
        $result = $this->service()->handle($businessB, 'private-key', ['amount' => 10], null, $this->countingCallback());

        $this->assertFalse($result['replayed']);
        $this->assertSame(2, $this->executions);
    }

    public function test_a_concurrent_duplicate_that_loses_the_insert_race_replays_the_winners_result(): void
    {
        $business = Business::factory()->create();
        $hash = hash('sha256', json_encode(['amount' => 10]));

        // Simulates the outcome of two simultaneous requests racing to
        // insert the same key: only one INSERT can win (the unique index
        // guarantees this even under real concurrency); here we simulate
        // the winner having already completed by the time the loser's
        // handle() call proceeds past its own failed insert attempt.
        $winner = IdempotencyKey::query()->create([
            'business_id' => $business->id,
            'client_transaction_id' => 'txn-race',
            'request_hash' => $hash,
            'response_status' => 201,
            'response_body' => ['created' => 'from-winner'],
        ]);

        $loserResult = $this->service()->handle($business, 'txn-race', ['amount' => 10], null, $this->countingCallback());

        $this->assertSame(0, $this->executions, 'the loser must not re-run the operation');
        $this->assertTrue($loserResult['replayed']);
        $this->assertSame('from-winner', $loserResult['body']['created']);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }
}
