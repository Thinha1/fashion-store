<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AdminPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Read-only audit trail: no create, edit or delete from the UI.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');
        $subjectTypes = AuditLog::query()->distinct()->orderBy('subject_type')->pluck('subject_type');
        $actors = User::query()->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))->orderBy('name')->get(['id', 'name', 'email']);

        $filters = [
            'action' => $actions->contains($request->query('action')) ? $request->query('action') : null,
            'subject_type' => $subjectTypes->contains($request->query('subject_type')) ? $request->query('subject_type') : null,
            'actor' => $this->actorFilter($request->query('actor'), $actors->modelKeys()),
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
        ];

        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($filters['action'], fn ($query, $action) => $query->where('action', $action))
            ->when($filters['subject_type'], fn ($query, $type) => $query->where('subject_type', $type))
            ->when($filters['actor'] === 'system', fn ($query) => $query->whereNull('actor_id'))
            ->when(is_int($filters['actor']), fn ($query) => $query->where('actor_id', $filters['actor']))
            ->when($filters['from'], fn ($query, $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'], fn ($query, $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('id')
            ->paginate(AdminPagination::perPage($request))
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => $actions,
            'subjectTypes' => $subjectTypes,
            'actors' => $actors,
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('actor:id,name,email');
        $old = $auditLog->old_values ?? [];
        $new = $auditLog->new_values ?? [];

        return view('admin.audit-logs.show', [
            'log' => $auditLog,
            'fields' => array_values(array_unique([...array_keys($old), ...array_keys($new)])),
            'old' => $old,
            'new' => $new,
        ]);
    }

    /**
     * @param  list<int>  $actorIds
     */
    private function actorFilter(mixed $value, array $actorIds): int|string|null
    {
        if ($value === 'system') {
            return 'system';
        }

        return is_numeric($value) && in_array((int) $value, $actorIds, true) ? (int) $value : null;
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
