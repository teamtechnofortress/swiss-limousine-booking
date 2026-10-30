<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\RbacException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Rbac\AdminUserService;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminUserController extends Controller
{
    public function __construct(private readonly AdminUserService $admins, private readonly RbacCatalog $catalog) {}

    public function index(): View
    {
        return view('admin.team.index', [
            'users' => User::query()->with('roles')->orderBy('name')->paginate(25),
            'roles' => $this->catalog->roles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(array_keys($this->catalog->roles()))],
        ]);
        $this->admins->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ], $data['roles'], $request->user());

        return back()->with('status', 'Admin user created.');
    }

    public function update(User $user, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);
        $this->admins->update($user, array_filter($data, fn ($value) => $value !== null && $value !== ''), $request->user());

        return back()->with('status', 'Admin user updated.');
    }

    public function syncRoles(User $user, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(array_keys($this->catalog->roles()))],
        ]);
        $this->admins->syncRoles($user, $data['roles'], $request->user());

        return back()->with('status', 'Roles updated.');
    }

    public function toggleStatus(User $user, Request $request): RedirectResponse
    {
        $deactivate = $user->is_active;

        try {
            $deactivate
                ? $this->admins->deactivate($user, $request->user())
                : $this->admins->activate($user, $request->user());
        } catch (RbacException $e) {
            return back()->withErrors(['team' => $e->getMessage()]);
        }

        return back()->with('status', $deactivate ? 'Admin user deactivated.' : 'Admin user activated.');
    }

    public function roles(): View
    {
        return view('admin.team.roles', [
            'roles' => $this->catalog->roles(),
            'permissionGroups' => $this->catalog->permissionGroups(),
        ]);
    }
}
