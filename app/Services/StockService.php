<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY code path allowed to change stock quantities. Every future
 * module that moves stock — Purchases, Sales, Returns, manual Adjustments,
 * Transfers — must go through StockService::adjust() rather than touching
 * the inventories/stock_movements tables directly, so a race between two
 * concurrent sales of the last unit is always resolved correctly.
 *
 * Concurrency: adjust() runs inside its own DB transaction and takes a
 * SELECT ... FOR UPDATE row lock on the (branch, product) inventory row
 * before checking/changing its quantity, so two simultaneous requests for
 * the same product+branch are serialized by the database itself rather than
 * racing in PHP. Callers building a larger transaction (e.g. a future Sale
 * with several line items) should call adjust() for each line from inside
 * their own already-open DB::transaction(); Laravel's transactions nest
 * safely via savepoints, so this remains one atomic unit of work.
 */
class StockService
{
    /**
     * @param  int  $quantity  Signed: positive increases stock, negative decreases it.
     */
    public function adjust(
        Branch $branch,
        Product $product,
        int $quantity,
        string $type,
        ?Model $reference = null,
        ?User $user = null,
        ?string $notes = null,
        bool $allowNegative = false,
    ): Inventory {
        if ($quantity === 0) {
            throw new \InvalidArgumentException('Stock adjustment quantity must not be zero.');
        }

        return DB::transaction(function () use ($branch, $product, $quantity, $type, $reference, $user, $notes, $allowNegative) {
            // firstOrCreate is safe against two concurrent callers racing to
            // create the same (branch, product) row for the first time — it
            // catches the resulting unique-constraint violation and re-reads
            // instead of erroring. Either way, we re-select WITH the row
            // lock immediately after, so the actual read-check-write below
            // is always serialized against other adjust() calls.
            Inventory::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'product_id' => $product->id],
                ['business_id' => $branch->business_id, 'quantity' => 0],
            );

            $inventory = Inventory::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newBalance = $inventory->quantity + $quantity;

            if (! $allowNegative && $newBalance < 0) {
                throw new InsufficientStockException($inventory->quantity, abs($quantity));
            }

            $inventory->quantity = $newBalance;
            $inventory->save();

            StockMovement::create([
                'business_id' => $branch->business_id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $newBalance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);

            return $inventory;
        });
    }

    public function currentQuantity(Branch $branch, Product $product): int
    {
        return (int) (Inventory::query()
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->value('quantity') ?? 0);
    }
}
