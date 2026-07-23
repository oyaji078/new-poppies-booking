<?php

namespace App\Livewire\Admin;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Super-admin-only staff account management.
 *
 * Creating accounts and assigning roles decides who can operate the hotel and
 * who can move money, so this screen is guarded three ways (route middleware,
 * mount, and every action) and enforces invariants that a mis-click could
 * otherwise use to lock everyone out: you cannot change your own role, disable
 * yourself, or remove the last super admin.
 */
class UserManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = UserRole::RECEPTIONIST->value;

    public string $password = '';

    public function mount(): void
    {
        $this->authorizeSuperAdmin();
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Halaman ini hanya dapat diakses oleh Super Admin.');
    }

    /**
     * Roles this screen manages — staff only. Customers self-register.
     *
     * @return array<string, string>
     */
    public function staffRoles(): array
    {
        return collect([
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN,
            UserRole::MANAGER,
            UserRole::RECEPTIONIST,
        ])->mapWithKeys(fn (UserRole $r) => [$r->value => $r->label()])->all();
    }

    public function openCreate(): void
    {
        $this->authorizeSuperAdmin();
        $this->reset(['editingId', 'name', 'email', 'phone', 'password']);
        $this->role = UserRole::RECEPTIONIST->value;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeSuperAdmin();
        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->role = $user->role->value;
        $this->password = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizeSuperAdmin();

        $editing = $this->editingId ? User::findOrFail($this->editingId) : null;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(array_keys($this->staffRoles()))],
            // Required on create, optional on edit (leave blank to keep it).
            'password' => [$editing ? 'nullable' : 'required', 'nullable', Password::min(8)],
        ], [], [
            'name' => 'nama', 'email' => 'email', 'role' => 'peran', 'password' => 'kata sandi',
        ]);

        // You cannot change your own role — prevents self-demotion lockout.
        if ($editing && $editing->id === auth()->id() && $editing->role->value !== $data['role']) {
            $this->addError('role', 'Anda tidak dapat mengubah peran akun Anda sendiri.');

            return;
        }

        // The last active super admin must keep the role.
        if ($editing && $editing->role->isSuperAdmin() && $data['role'] !== UserRole::SUPER_ADMIN->value
            && $this->activeSuperAdminCount() <= 1) {
            $this->addError('role', 'Tidak dapat menurunkan Super Admin terakhir.');

            return;
        }

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'role' => $data['role'],
        ];
        if (! empty($data['password'])) {
            $attributes['password'] = $data['password']; // hashed by cast
        }

        if ($editing) {
            $before = ['role' => $editing->role->value, 'email' => $editing->email];
            $editing->update($attributes);
            $audit->log(AuditAction::USER_MANAGED->value, $editing, $before, [
                'action' => 'updated',
                'email' => $editing->email,
                'role' => $editing->role->value,
                'password_reset' => ! empty($data['password']),
            ]);
        } else {
            $attributes['is_active'] = true;
            $attributes['email_verified_at'] = now();
            $user = User::create($attributes);
            $audit->log(AuditAction::USER_MANAGED->value, $user, null, [
                'action' => 'created',
                'email' => $user->email,
                'role' => $user->role->value,
            ]);
        }

        $this->showForm = false;
        $this->reset(['editingId', 'name', 'email', 'phone', 'password']);
        session()->flash('status', 'Data pengguna disimpan.');
    }

    public function toggleActive(int $id, AuditLogger $audit): void
    {
        $this->authorizeSuperAdmin();
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            $this->addError('general', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');

            return;
        }

        // Never disable the last active super admin.
        if ($user->is_active && $user->role->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            $this->addError('general', 'Tidak dapat menonaktifkan Super Admin terakhir.');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        $audit->log(AuditAction::USER_MANAGED->value, $user, null, [
            'action' => $user->is_active ? 'activated' : 'deactivated',
            'email' => $user->email,
        ]);

        session()->flash('status', $user->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('role', UserRole::SUPER_ADMIN->value)
            ->where('is_active', true)
            ->count();
    }

    public function render(): View
    {
        $users = User::query()
            ->whereIn('role', array_keys($this->staffRoles()))
            ->orderByRaw(
                "CASE role
                    WHEN 'super_admin' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'manager' THEN 3
                    WHEN 'receptionist' THEN 4
                    ELSE 5
                END"
            )
            ->orderBy('name')
            ->get();

        return view('livewire.admin.user-manager', [
            'users' => $users,
            'roles' => $this->staffRoles(),
        ])->layout('components.layouts.admin', [
            'title' => 'Kelola Pengguna',
            'heading' => 'Kelola Pengguna',
            'breadcrumb' => 'Admin / Kelola Pengguna',
        ]);
    }
}
