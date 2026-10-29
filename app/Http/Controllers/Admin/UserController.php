<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreAdminUserRequest;
use App\Http\Requests\Admin\Users\SyncAdminRolesRequest;
use App\Http\Requests\Admin\Users\UpdateAdminUserRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use App\Services\Rbac\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UserController extends Controller
{
    public function __construct(private readonly AdminUserService $admins) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);

        $users = User::query()
            ->with('roles')
            ->when(! empty($filters['q']), function ($query) use ($filters): void {
                $term = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return AdminUserResource::collection($users);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = $this->admins->create(
            ['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']],
            $data['roles'],
            $this->actor($request),
        );

        return (new AdminUserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): AdminUserResource
    {
        return new AdminUserResource($user->load('roles'));
    }

    public function update(User $user, UpdateAdminUserRequest $request): AdminUserResource
    {
        return new AdminUserResource(
            $this->admins->update($user, $request->validated(), $this->actor($request)),
        );
    }

    public function syncRoles(User $user, SyncAdminRolesRequest $request): AdminUserResource
    {
        return new AdminUserResource(
            $this->admins->syncRoles($user, $request->validated()['roles'], $this->actor($request)),
        );
    }

    public function deactivate(User $user, Request $request): AdminUserResource
    {
        return new AdminUserResource($this->admins->deactivate($user, $this->actor($request)));
    }

    public function activate(User $user, Request $request): AdminUserResource
    {
        return new AdminUserResource($this->admins->activate($user, $this->actor($request)));
    }

    private function actor(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor;
    }
}
