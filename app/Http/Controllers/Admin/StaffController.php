<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStaffRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Staff\StaffAccess;
use App\Support\AdminPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Staff accounts: every user whose role is not "customer". Staff create the
 * account with a role and a first password; locking keeps the account and
 * its history but takes every permission away (User::hasPermission).
 */
class StaffController extends Controller
{
    public function __construct(private readonly StaffAccess $access) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q')) ?: null,
            'role' => $request->integer('role') ?: null,
            'status' => in_array($request->query('status'), ['active', 'locked'], true) ? $request->query('status') : null,
        ];

        $staff = User::query()
            ->with('role')
            ->whereHas('role', fn ($query) => $query->where('code', '!=', 'customer'))
            ->where('status', '!=', User::STATUS_SYSTEM)
            ->when($filters['q'], fn ($query, string $q) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->when($filters['role'], fn ($query, int $role) => $query->where('role_id', $role))
            ->when($filters['status'] === 'locked', fn ($query) => $query->where('status', 'locked'))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('status', '!=', 'locked'))
            ->orderBy('name')
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.staff.index', ['staff' => $staff, 'roles' => $this->access->staffRoles(), 'filters' => $filters]);
    }

    public function create(): View
    {
        return view('admin.staff.create', ['staff' => new User(['status' => 'active']), 'roles' => $this->access->staffRoles()]);
    }

    public function store(SaveStaffRequest $request): RedirectResponse
    {
        $role = Role::query()->with('permissions')->findOrFail($request->integer('role_id'));
        if ($problem = $this->access->checkStaffChange($request->user(), null, $role, $request->validated('status'))) {
            return back()->withInput()->with('error', $problem);
        }

        $user = new User($request->safe()->only(['name', 'email', 'phone', 'status']));
        $user->role_id = $role->id;
        $user->password = Hash::make($request->validated('password'));
        $user->email_verified_at = now();
        $user->save();

        app(AuditLogger::class)->record('staff.created', $user, null, $this->auditValues($user));

        return redirect()->route('admin.staff.index')->with('status', "Đã tạo tài khoản cho {$user->name}.");
    }

    public function edit(User $staff): View
    {
        $this->ensureStaff($staff);

        return view('admin.staff.edit', ['staff' => $staff, 'roles' => $this->access->staffRoles()]);
    }

    public function update(SaveStaffRequest $request, User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        $role = Role::query()->with('permissions')->findOrFail($request->integer('role_id'));
        if ($problem = $this->access->checkStaffChange($request->user(), $staff, $role, $request->validated('status'))) {
            return back()->withInput()->with('error', $problem);
        }

        $before = $this->auditValues($staff);
        $staff->fill($request->safe()->only(['name', 'email', 'phone', 'status']));
        $staff->role_id = $role->id;
        $passwordChanged = $request->filled('password');
        if ($passwordChanged) {
            $staff->password = Hash::make($request->validated('password'));
        }
        $staff->save();
        $staff->load('role');

        $after = $this->auditValues($staff);
        $changed = array_keys(array_diff_assoc($after, $before));
        if ($changed !== [] || $passwordChanged) {
            // Never the password itself — only that it changed.
            app(AuditLogger::class)->record(
                $before['status'] !== $after['status'] ? ($after['status'] === 'locked' ? 'staff.locked' : 'staff.unlocked') : 'staff.updated',
                $staff,
                array_intersect_key($before, array_flip($changed)),
                array_intersect_key($after, array_flip($changed)) + ($passwordChanged ? ['password_changed' => true] : []),
            );
        }

        return redirect()->route('admin.staff.index')->with('status', "Đã lưu tài khoản {$staff->name}.");
    }

    /**
     * Customers are not managed here; their accounts never show on this screen.
     */
    private function ensureStaff(User $staff): void
    {
        abort_if($staff->role?->code === 'customer' || $staff->status === User::STATUS_SYSTEM, 404);
    }

    /**
     * @return array<string, ?string>
     */
    private function auditValues(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role()->value('name'),
            'status' => $user->status === 'locked' ? 'locked' : 'active',
        ];
    }
}
