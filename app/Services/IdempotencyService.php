<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Device;
use App\Models\IdempotencyKey;
use App\Support\IdempotencyConflictException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The single mechanism every future write endpoint that Electron might
 * retry (Sales today; Returns, Purchases-via-Electron, etc. tomorrow) must
 * go through when the client supplies a client_transaction_id. Guarantees:
 *
 *  - Same key + same business + same payload, submitted twice: the
 *    operation runs at most once; the second call replays the first
 *    call's recorded response instead of re-executing it.
 *  - Same key + same business + a DIFFERENT payload: rejected with
 *    IdempotencyConflictException (409) — a key is a promise about one
 *    specific transaction, not a slot to overwrite.
 *  - Same key, different business: not the same row at all (the unique
 *    index is scoped per business), so no collision is even possible —
 *    one business can never observe or block another's key.
 *  - Two truly concurrent requests with the same key: only one inserts
 *    the row (the loser's INSERT hits the unique constraint and is caught
 *    below); both then contend for the same row's lock, so the second is
 *    blocked until the first's transaction — which runs the actual
 *    business logic and records the response — commits, at which point
 *    the second sees a completed record and replays it rather than
 *    running the operation again.
 */
class IdempotencyService
{
    /**
     * @param  array<string, mixed>  $payload  Hashed to detect a reused key submitted with different data.
     * @param  callable(IdempotencyKey $record): array{0: int, 1: mixed}  $callback  Runs the actual operation; returns [httpStatus, responseBody].
     * @return array{status: int, body: mixed, replayed: bool}
     */
    public function handle(
        Business $business,
        string $clientTransactionId,
        array $payload,
        ?Device $device,
        callable $callback,
    ): array {
        return DB::transaction(function () use ($business, $clientTransactionId, $payload, $device, $callback) {
            $hash = $this->hash($payload);

            $record = $this->lockedRecord($business, $clientTransactionId);

            if (! $record) {
                try {
                    IdempotencyKey::query()->create([
                        'business_id' => $business->id,
                        'device_id' => $device?->id,
                        'client_transaction_id' => $clientTransactionId,
                        'request_hash' => $hash,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Lost the race to a concurrent identical request. Its
                    // transaction holds the row lock until it commits; the
                    // lockedRecord() call below blocks here until then.
                }

                $record = $this->lockedRecord($business, $clientTransactionId);
            }

            if ($record->request_hash !== $hash) {
                throw new IdempotencyConflictException();
            }

            if ($record->isCompleted()) {
                return ['status' => $record->response_status, 'body' => $record->response_body, 'replayed' => true];
            }

            [$status, $body] = $callback($record);

            $record->update(['response_status' => $status, 'response_body' => $body]);

            return ['status' => $status, 'body' => $body, 'replayed' => false];
        });
    }

    private function lockedRecord(Business $business, string $clientTransactionId): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('business_id', $business->id)
            ->where('client_transaction_id', $clientTransactionId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload));
    }
}
