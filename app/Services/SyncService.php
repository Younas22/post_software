<?php

namespace App\Services;

use App\Support\SyncCursor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Generic incremental-sync pagination for any tenant-scoped, timestamped
 * resource (Products, Categories, Brands, Customers, Inventory, ...).
 *
 * Strategy: an `updated_at` cursor taken from the SERVER's clock, captured
 * fresh at the start of each call and handed back as the cursor for the
 * client's next request. A request with no cursor is treated as an initial
 * sync (return everything currently in scope); one with a cursor returns
 * only rows with `updated_at` strictly after it. There is no upper bound on
 * that filter, so a row modified between when the cursor was captured and
 * when the query actually runs is simply included a cycle early — safe,
 * and never a reason a change could be missed (the only failure mode this
 * design avoids: silently skipping a row, never over-including one).
 */
class SyncService
{
    /**
     * @param  Builder  $query  Already scoped to the tenant/branch (and any other resource-specific filter) — NOT yet ordered or since-filtered.
     * @param  (callable(Carbon): Builder)|null  $deletedQuery  Given the decoded cursor, returns a query for soft-deleted rows in the same scope (e.g. `Product::onlyTrashed()->where('business_id', ...)`). Pass null for a resource with no soft deletes (e.g. Inventory).
     * @param  callable(mixed): array  $transform  Shapes one row of $query's model into its wire representation.
     * @return array{data: array, deleted_ids: array<int, int>, cursor: string, synced_at: string, meta: array}
     */
    public function paginate(
        Builder $query,
        ?string $since,
        int $perPage,
        callable $transform,
        ?callable $deletedQuery = null,
    ): array {
        $asOf = now();
        $sinceAt = $since !== null ? SyncCursor::decode($since) : null;

        if ($sinceAt !== null) {
            $query->where('updated_at', '>', $sinceAt);
        }

        $paginated = $query->orderBy('updated_at')->orderBy('id')->paginate($perPage);

        $deletedIds = [];
        if ($sinceAt !== null && $deletedQuery !== null) {
            $deletedIds = $deletedQuery($sinceAt)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return [
            'data' => $paginated->getCollection()->map($transform)->values()->all(),
            'deleted_ids' => $deletedIds,
            'cursor' => SyncCursor::encode($asOf),
            'synced_at' => $asOf->toIso8601String(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'has_more' => $paginated->hasMorePages(),
            ],
        ];
    }
}
