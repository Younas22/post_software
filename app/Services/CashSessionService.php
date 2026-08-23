<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * All cash-drawer math happens here, server-side, from the session's own
 * recorded movements and the actual sales rung up during it — never from a
 * frontend-supplied total. See the cash_sessions migration's comment for
 * how "one open session per cashier per branch" is enforced at the
 * database level, not just checked in application code.
 */
class CashSessionService
{
    public function open(Branch $branch, User $user, float $openingCash, ?string $notes): CashSession
    {
        try {
            return DB::transaction(function () use ($branch, $user, $openingCash, $notes) {
                $session = new CashSession();
                $session->business_id = $branch->business_id;
                $session->branch_id = $branch->id;
                $session->user_id = $user->id;
                $session->fill([
                    'opening_cash' => $openingCash,
                    'status' => 'open',
                    'open_marker' => 1,
                    'notes' => $notes,
                    'opened_at' => now(),
                ]);
                $session->save();

                return $session;
            });
        } catch (UniqueConstraintViolationException) {
            throw new InvalidStateTransitionException('You already have an open cash session for this branch.');
        }
    }

    public function current(Branch $branch, User $user): ?CashSession
    {
        return CashSession::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();
    }

    public function cashIn(CashSession $session, float $amount, ?string $reason, User $actor): CashSession
    {
        return $this->recordMovement($session, 'cash_in', $amount, $reason, $actor);
    }

    public function cashOut(CashSession $session, float $amount, ?string $reason, User $actor): CashSession
    {
        return $this->recordMovement($session, 'cash_out', $amount, $reason, $actor);
    }

    private function recordMovement(CashSession $session, string $type, float $amount, ?string $reason, User $actor): CashSession
    {
        return DB::transaction(function () use ($session, $type, $amount, $reason, $actor) {
            $session = CashSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isOpen()) {
                throw new InvalidStateTransitionException('This cash session is not open.');
            }

            $session->movements()->create([
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'user_id' => $actor->id,
            ]);

            return $session->fresh('movements');
        });
    }

    public function close(CashSession $session, float $actualCash, User $actor): CashSession
    {
        return DB::transaction(function () use ($session, $actualCash, $actor) {
            $session = CashSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isOpen()) {
                throw new InvalidStateTransitionException('This cash session is already closed.');
            }

            $cashIn = (float) $session->movements()->where('type', 'cash_in')->sum('amount');
            $cashOut = (float) $session->movements()->where('type', 'cash_out')->sum('amount');
            $cashSales = (float) Payment::query()
                ->whereHas('paymentMethod', fn ($q) => $q->where('slug', 'cash'))
                ->whereHas('sale', function ($q) use ($session) {
                    $q->where('branch_id', $session->branch_id)
                        ->where('user_id', $session->user_id)
                        ->where('status', 'completed')
                        ->where('sale_date', '>=', $session->opened_at);
                })
                ->sum('amount');

            $expected = round((float) $session->opening_cash + $cashSales + $cashIn - $cashOut, 2);

            $session->closing_cash = $actualCash;
            $session->expected_cash = $expected;
            $session->actual_cash = $actualCash;
            $session->difference = round($actualCash - $expected, 2);
            $session->status = 'closed';
            $session->open_marker = null;
            $session->closed_at = now();
            $session->save();

            return $session->fresh('movements');
        });
    }
}
