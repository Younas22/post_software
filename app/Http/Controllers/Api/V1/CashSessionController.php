<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OpenCashSessionRequest;
use App\Models\Branch;
use App\Models\CashSession;
use App\Services\CashSessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CashSession::class);

        $actor = $request->user();

        $sessions = CashSession::query()
            ->with(['user:id,name', 'branch:id,name'])
            ->whereHas('branch', function ($query) use ($actor) {
                $query->whereHas('business', fn ($q) => $q->where('client_id', $actor->client_id));
                if ($actor->business_id !== null) {
                    $query->where('business_id', $actor->business_id);
                }
                if ($actor->branch_id !== null) {
                    $query->where('id', $actor->branch_id);
                }
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('opened_at')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return ApiResponse::collection($sessions, 'Cash sessions retrieved successfully.');
    }

    public function current(Request $request, CashSessionService $sessions): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $this->authorize('open', [CashSession::class, $branch]);

        $session = $sessions->current($branch, $request->user());

        return ApiResponse::success($session?->load('movements'), $session ? 'Current cash session retrieved.' : 'No open cash session for this branch.');
    }

    public function open(OpenCashSessionRequest $request, CashSessionService $sessions): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $this->authorize('open', [CashSession::class, $branch]);

        $session = $sessions->open(
            branch: $branch,
            user: $request->user(),
            openingCash: (float) $request->input('opening_cash'),
            notes: $request->string('notes')->value() ?: null,
        );

        return ApiResponse::success($session, 'Cash session opened.', 201);
    }

    public function cashIn(Request $request, CashSession $cashSession, CashSessionService $sessions): JsonResponse
    {
        $this->authorize('operate', $cashSession);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'reason' => ['nullable', 'string', 'max:255']]);

        $session = $sessions->cashIn($cashSession, (float) $data['amount'], $data['reason'] ?? null, $request->user());

        return ApiResponse::success($session, 'Cash in recorded.');
    }

    public function cashOut(Request $request, CashSession $cashSession, CashSessionService $sessions): JsonResponse
    {
        $this->authorize('operate', $cashSession);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'reason' => ['nullable', 'string', 'max:255']]);

        $session = $sessions->cashOut($cashSession, (float) $data['amount'], $data['reason'] ?? null, $request->user());

        return ApiResponse::success($session, 'Cash out recorded.');
    }

    public function close(Request $request, CashSession $cashSession, CashSessionService $sessions): JsonResponse
    {
        $this->authorize('operate', $cashSession);
        $data = $request->validate(['actual_cash' => ['required', 'numeric', 'min:0']]);

        $session = $sessions->close($cashSession, (float) $data['actual_cash'], $request->user());

        return ApiResponse::success($session, 'Cash session closed.');
    }

    public function show(CashSession $cashSession): JsonResponse
    {
        $this->authorize('view', $cashSession);

        return ApiResponse::success($cashSession->load(['movements', 'user:id,name', 'branch:id,name']), 'Cash session retrieved.');
    }
}
