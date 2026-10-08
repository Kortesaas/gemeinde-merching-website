<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

/**
 * Minimal account management: create (the person sets their own password via
 * e-mail link and enrols MFA at first login), edit roles, (de)activate.
 * Accounts are never deleted.
 */
class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.users.index', ['users' => User::query()->with('roles')->orderBy('name')->paginate(50)]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => Role::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $data = $this->validated($request, null);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                // Random unusable password; the person sets their own via the link.
                'password' => Str::password(64),
                'is_active' => true,
            ]);
            $user->syncRoles($data['roles'] ?? []);
            $this->audit->record('user.created', $user, ['via' => 'admin', 'roles' => $data['roles'] ?? []]);

            return $user;
        });

        Password::broker()->sendResetLink(['email' => $user->email], function (User $u, string $token) {
            defer(fn () => $u->sendPasswordResetNotification($token));
        });

        return redirect()->route('admin.user.edit', $user)->with('status', 'Konto angelegt. Ein Link zum Festlegen des Passworts wurde per E-Mail versendet.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', ['user' => $user, 'roles' => Role::cases()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $data = $this->validated($request, $user);
        /** @var User $actor */
        $actor = $request->user();

        $roles = $data['roles'] ?? [];
        $active = (bool) ($data['is_active'] ?? false);

        if ($actor->is($user) && (! $active || ! in_array(Role::Administrator->value, $roles, true)) && $user->hasRole(Role::Administrator->value)) {
            throw ValidationException::withMessages(['roles' => 'Sie können sich nicht selbst die Administration entziehen oder Ihr eigenes Konto deaktivieren.']);
        }

        if ($user->hasRole(Role::Administrator->value) && (! $active || ! in_array(Role::Administrator->value, $roles, true))
            && User::role(Role::Administrator->value)->where('is_active', true)->whereKeyNot($user->getKey())->doesntExist()) {
            throw ValidationException::withMessages(['roles' => 'Es muss mindestens ein aktives Administrationskonto bestehen bleiben.']);
        }

        DB::transaction(function () use ($user, $data, $roles, $active) {
            $before = $user->getRoleNames()->all();
            $user->fill(['name' => $data['name'], 'email' => $data['email'], 'is_active' => $active])->save();
            $user->syncRoles($roles);

            $this->audit->record('user.updated', $user, ['changed' => array_keys($user->getChanges())]);
            if ($before !== $roles) {
                $this->audit->record('user.roles_changed', $user, [
                    'added' => array_values(array_diff($roles, $before)),
                    'removed' => array_values(array_diff($before, $roles)),
                ]);
            }
            if (! $active) {
                DB::table((string) config('session.table'))->where('user_id', $user->getKey())->delete();
            }
        });

        return redirect()->route('admin.user.edit', $user)->with('status', 'Konto wurde gespeichert.');
    }

    /**
     * @return array{name: string, email: string, roles?: list<string>, is_active?: bool}
     */
    private function validated(Request $request, ?User $user): array
    {
        /** @var array{name: string, email: string, roles?: list<string>, is_active?: bool} */
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user?->getKey())],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(array_map(fn (Role $r) => $r->value, Role::cases()))],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['name' => 'Name', 'email' => 'E-Mail-Adresse', 'roles' => 'Rollen']);
    }
}
