<?php

namespace App\Http\Controllers;

use App\Actions\Administration\CreateAdministrativeUser;
use App\Actions\Administration\GenerateReferenceDataCode;
use App\Actions\Administration\UpdateAdministrativeUser;
use App\Actions\Audit\RecordAuditEvent;
use App\CurrencyConverter;
use App\Http\Requests\Administration\DepartmentRequest;
use App\Http\Requests\Administration\PermissionRequest;
use App\Http\Requests\Administration\PositionRequest;
use App\Http\Requests\Administration\ReferenceDataRequest;
use App\Http\Requests\Administration\RoleRequest;
use App\Http\Requests\Administration\StoreAdministrativeUserRequest;
use App\Http\Requests\Administration\UpdateAdministrativeUserRequest;
use App\Http\Requests\Administration\UpdateFirmSettingsRequest;
use App\Models\BillingItem;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\GeneralSetting;
use App\Models\MatterType;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AdministrationController extends Controller
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function index(): RedirectResponse
    {
        return to_route('administration.firm');
    }

    public function firm(): Response
    {
        return Inertia::render('administration/firm', [
            'firmSettings' => $this->firmSettings(),
            'currencies' => CurrencyConverter::SupportedCurrencies,
        ]);
    }

    public function users(): Response
    {
        return Inertia::render('administration/users', [
            'users' => $this->usersPayload(),
            'roles' => $this->rolesPayload(),
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'positions' => Position::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'department_id', 'name']),
        ]);
    }

    public function departments(): Response
    {
        return Inertia::render('administration/departments', [
            'departments' => Department::query()
                ->withCount('users')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'description', 'is_active']),
        ]);
    }

    public function positions(): Response
    {
        return Inertia::render('administration/positions', [
            'positions' => Position::query()
                ->with('department:id,name')
                ->orderBy('department_id')
                ->orderBy('name')
                ->get(['id', 'department_id', 'name', 'code', 'description', 'is_active', 'is_attorney']),
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function access(): RedirectResponse
    {
        return to_route('administration.roles.index');
    }

    public function roles(): Response
    {
        return Inertia::render('administration/roles', [
            'roles' => $this->rolesPayload(),
            'permissions' => $this->permissionsPayload(),
        ]);
    }

    public function permissions(): Response
    {
        return Inertia::render('administration/permissions', [
            'permissions' => $this->permissionsPayload(),
        ]);
    }

    public function catalog(): RedirectResponse
    {
        return to_route('administration.matter-types.index');
    }

    public function matterTypes(): Response
    {
        return Inertia::render('administration/matter-types', [
            'matterTypes' => MatterType::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function billingItems(): Response
    {
        return Inertia::render('administration/billing-items', [
            'billingItems' => BillingItem::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function expenseCategories(): Response
    {
        return Inertia::render('administration/expense-categories', [
            'expenseCategories' => ExpenseCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function paymentMethods(): Response
    {
        return Inertia::render('administration/payment-methods', [
            'paymentMethods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function updateFirmSettings(UpdateFirmSettingsRequest $request): RedirectResponse
    {
        $settings = GeneralSetting::query()->firstOrNew();
        $settings->fill($request->validated())->save();
        $this->audit->handle($request->user(), 'firm.updated', 'Updated firm settings.');

        return $this->success('Firm settings saved.', 'administration.firm');
    }

    public function storeUser(StoreAdministrativeUserRequest $request, CreateAdministrativeUser $createUser): RedirectResponse
    {
        $user = $createUser->handle($this->createAdministrativeUserAttributes($request));
        $this->audit->handle($request->user(), 'user.created', 'Created user '.$user->name.'.', $user);

        return $this->success('User created. They will be asked to choose a new password at first sign-in.', 'administration.users.index');
    }

    public function updateUser(
        UpdateAdministrativeUserRequest $request,
        User $user,
        UpdateAdministrativeUser $updateUser,
    ): RedirectResponse {
        $attributes = $this->administrativeUserAttributes($request);

        if ($user->is($request->user()) && $attributes['status'] !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'You cannot deactivate your own administration account.',
            ]);
        }

        $updateUser->handle($user, $attributes);
        $this->audit->handle($request->user(), 'user.updated', 'Updated user '.$user->name.'.', $user);

        return $this->success('User access updated.', 'administration.users.index');
    }

    public function storeDepartment(DepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());
        $this->audit->handle($request->user(), 'department.created', 'Created department '.$department->name.'.', $department);

        return $this->success('Department created.', 'administration.departments.index');
    }

    public function updateDepartment(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());
        $this->audit->handle($request->user(), 'department.updated', 'Updated department '.$department->name.'.', $department);

        return $this->success('Department updated.', 'administration.departments.index');
    }

    public function storePosition(
        PositionRequest $request,
        GenerateReferenceDataCode $generateReferenceDataCode,
    ): RedirectResponse {
        $attributes = $request->validated();
        $attributes['code'] = $generateReferenceDataCode->handle(Position::class, $attributes['name']);
        $position = Position::create($attributes);
        $this->audit->handle($request->user(), 'position.created', 'Created position '.$position->name.'.', $position);

        return $this->success('Position created.', 'administration.positions.index');
    }

    public function updatePosition(
        PositionRequest $request,
        Position $position,
        GenerateReferenceDataCode $generateReferenceDataCode,
    ): RedirectResponse {
        $attributes = $request->validated();
        $attributes['code'] = $generateReferenceDataCode->handle(Position::class, $attributes['name'], $position->id);
        $position->update($attributes);
        $this->audit->handle($request->user(), 'position.updated', 'Updated position '.$position->name.'.', $position);

        return $this->success('Position updated.', 'administration.positions.index');
    }

    public function storeRole(RoleRequest $request): RedirectResponse
    {
        $role = Role::create(Arr::only($request->validated(), ['name', 'description']));
        $this->syncRolePermissions($role, $request->validated('permission_ids'));
        $this->audit->handle($request->user(), 'role.created', 'Created role '.$role->name.'.', $role);

        return $this->success('Role created.', 'administration.roles.index');
    }

    public function updateRole(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->update(Arr::only($request->validated(), ['name', 'description']));
        $this->syncRolePermissions($role, $request->validated('permission_ids'));
        $this->audit->handle($request->user(), 'role.updated', 'Updated role '.$role->name.'.', $role);

        return $this->success('Role updated.', 'administration.roles.index');
    }

    public function storePermission(PermissionRequest $request): RedirectResponse
    {
        $permission = Permission::create($request->validated());
        $this->audit->handle($request->user(), 'permission.created', 'Created permission '.$permission->name.'.', $permission);

        return $this->success('Permission created.', 'administration.permissions.index');
    }

    public function updatePermission(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update($request->validated());
        $this->audit->handle($request->user(), 'permission.updated', 'Updated permission '.$permission->name.'.', $permission);

        return $this->success('Permission updated.', 'administration.permissions.index');
    }

    public function storeReferenceData(
        ReferenceDataRequest $request,
        string $resource,
        GenerateReferenceDataCode $generateReferenceDataCode,
    ): RedirectResponse {
        $model = $this->referenceDataModel($resource);
        $attributes = $request->validated();
        $attributes['code'] = $generateReferenceDataCode->handle($model, $attributes['name']);
        $referenceData = $model::create($attributes);
        $this->audit->handle($request->user(), 'reference_data.created', 'Created configuration item '.$attributes['name'].'.', $referenceData);

        return $this->success('Configuration item created.', $this->referenceDataRoute($resource));
    }

    public function updateReferenceData(
        ReferenceDataRequest $request,
        string $resource,
        int $record,
        GenerateReferenceDataCode $generateReferenceDataCode,
    ): RedirectResponse {
        $model = $this->referenceDataModel($resource);
        $referenceData = $model::query()->findOrFail($record);
        $attributes = $request->validated();
        $attributes['code'] = $generateReferenceDataCode->handle(
            $model,
            $attributes['name'],
            (int) $referenceData->getKey(),
        );
        $referenceData->update($attributes);
        $this->audit->handle($request->user(), 'reference_data.updated', 'Updated configuration item '.$attributes['name'].'.', $referenceData);

        return $this->success('Configuration item updated.', $this->referenceDataRoute($resource));
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    private function syncRolePermissions(Role $role, array $permissionIds): void
    {
        $role->assignedPermissions()->sync($permissionIds);
        $role->update([
            'permissions' => Permission::query()->whereKey($permissionIds)->pluck('key')->all(),
        ]);
    }

    /**
     * @return class-string<Model>
     */
    private function referenceDataModel(string $resource): string
    {
        return match ($resource) {
            'matter-types' => MatterType::class,
            'billing-items' => BillingItem::class,
            'expense-categories' => ExpenseCategory::class,
            'payment-methods' => PaymentMethod::class,
            default => throw new InvalidArgumentException('Unsupported reference-data resource.'),
        };
    }

    private function referenceDataRoute(string $resource): string
    {
        return match ($resource) {
            'matter-types' => 'administration.matter-types.index',
            'billing-items' => 'administration.billing-items.index',
            'expense-categories' => 'administration.expense-categories.index',
            'payment-methods' => 'administration.payment-methods.index',
            default => throw new InvalidArgumentException('Unsupported reference-data resource.'),
        };
    }

    /**
     * @return array{name: string, email: string, phone: string|null, department_id: int|null, position_id: int|null, status: string, role_ids: list<int>}
     */
    private function administrativeUserAttributes(FormRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->filled('phone') ? $request->string('phone')->toString() : null,
            'department_id' => $request->integer('department_id') ?: null,
            'position_id' => $request->integer('position_id') ?: null,
            'status' => $request->string('status')->toString(),
            'role_ids' => array_values(array_map(
                fn (mixed $roleId): int => (int) $roleId,
                $request->array('role_ids'),
            )),
        ];
    }

    /**
     * @return array{name: string, email: string, password: string, phone: string|null, department_id: int|null, position_id: int|null, status: string, role_ids: list<int>}
     */
    private function createAdministrativeUserAttributes(StoreAdministrativeUserRequest $request): array
    {
        $attributes = $this->administrativeUserAttributes($request);

        return [
            ...$attributes,
            'password' => $request->string('password')->toString(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function firmSettings(): array
    {
        $firmSettings = GeneralSetting::query()->first();

        return [
            'firm_name' => $firmSettings?->firm_name,
            'email' => $firmSettings?->email,
            'phone' => $firmSettings?->phone,
            'website' => $firmSettings?->website,
            'address_line1' => $firmSettings?->address_line1,
            'address_line2' => $firmSettings?->address_line2,
            'city' => $firmSettings?->city,
            'state' => $firmSettings?->state,
            'postal_code' => $firmSettings?->postal_code,
            'country' => $firmSettings?->country,
            'tax_id' => $firmSettings?->tax_id,
            'license_number' => $firmSettings?->license_number,
            'timezone' => $firmSettings === null ? 'Africa/Blantyre' : $firmSettings->timezone,
            'currency' => $firmSettings === null ? 'MWK' : $firmSettings->currency,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, email: string, phone: string|null, department: string|null, position: string|null, status: string, email_verified_at: Carbon|null, role_ids: list<int>, roles: Collection<int, string>}>
     */
    private function usersPayload(): array
    {
        return User::query()
            ->with(['roles:id,name', 'assignedDepartment:id,name', 'assignedPosition:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'department', 'department_id', 'position_id', 'status', 'email_verified_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'department_id' => $user->department_id,
                'department' => $user->department_id === null
                    ? $user->department
                    : $user->assignedDepartment->name,
                'position_id' => $user->position_id,
                'position' => $user->assignedPosition?->name,
                'status' => $user->status,
                'email_verified_at' => $user->email_verified_at,
                'role_ids' => array_values(array_map(
                    fn (int|string $roleId): int => (int) $roleId,
                    $user->roles->modelKeys(),
                )),
                'roles' => $user->roles->pluck('name')->values(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, description: string|null, user_count: int<0, max>, permission_ids: list<int>, permissions: Collection<int, array{key: string, name: string, group: string}>}>
     */
    private function rolesPayload(): array
    {
        return Role::query()
            ->with('assignedPermissions:id,key,name,group')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'user_count' => $role->users_count,
                'permission_ids' => array_values(array_map(
                    fn (int|string $permissionId): int => (int) $permissionId,
                    $role->assignedPermissions->modelKeys(),
                )),
                'permissions' => $role->assignedPermissions->map(fn (Permission $permission): array => [
                    'key' => $permission->key,
                    'name' => $permission->name,
                    'group' => $permission->group,
                ])->values(),
            ])
            ->all();
    }

    /**
     * @return array<int, Permission>
     */
    private function permissionsPayload(): array
    {
        return Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'group', 'description'])
            ->all();
    }

    private function success(string $message, string $route): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route($route);
    }
}
