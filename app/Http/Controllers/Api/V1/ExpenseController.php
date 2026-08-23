<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Requests\Api\V1\UpdateExpenseRequest;
use App\Models\Branch;
use App\Models\Expense;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $actor = $request->user();
        $search = trim((string) $request->query('search', ''));

        $expenses = Expense::query()
            ->with(['branch:id,name', 'user:id,name'])
            ->whereHas('business', function ($query) use ($actor) {
                $query->where('client_id', $actor->client_id);
                if ($actor->business_id !== null) {
                    $query->where('id', $actor->business_id);
                }
            })
            ->when($actor->branch_id !== null, fn ($q) => $q->where('branch_id', $actor->branch_id))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->string('date_to')))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->latest('expense_date')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($expenses, 'Expenses retrieved successfully.');
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $this->authorize('create', [Expense::class, $branch]);

        $expense = new Expense();
        $expense->business_id = $branch->business_id;
        $expense->branch_id = $branch->id;
        $expense->user_id = $request->user()->id;
        $expense->fill($request->safe()->only(['title', 'category', 'amount', 'expense_date', 'notes']));
        $expense->save();

        return ApiResponse::success($expense->load(['branch:id,name', 'user:id,name']), 'Expense recorded successfully.', 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        return ApiResponse::success($expense->load(['branch:id,name', 'user:id,name']), 'Expense retrieved successfully.');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $this->authorize('update', $expense);

        $expense->fill($request->safe()->only(['title', 'category', 'amount', 'expense_date', 'notes']));
        $expense->save();

        return ApiResponse::success($expense->load(['branch:id,name', 'user:id,name']), 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return ApiResponse::success(null, 'Expense deleted successfully.');
    }
}
