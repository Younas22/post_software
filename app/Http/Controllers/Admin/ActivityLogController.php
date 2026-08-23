<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ActivityLog::class);

        $search = trim((string) $request->query('search', ''));
        $action = $request->query('action', '');

        $logs = ActivityLog::query()
            ->with(['client', 'user'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%");
                });
            })
            ->when(in_array($action, ActivityLog::ACTIONS, true), fn ($query) => $query->where('action', $action))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'search' => $search,
            'action' => $action,
        ]);
    }
}
