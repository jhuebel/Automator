<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserManagement extends Component
{
    public bool $isAdding = false;

    public ?int $editingId = null;

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $role = '';

    public ?int $confirmingDeleteId = null;

    #[Computed]
    public function users()
    {
        return User::with('roles')->orderBy('username')->get();
    }

    #[Computed]
    public function roles()
    {
        return Role::orderBy('name')->pluck('name');
    }

    public function isProtected(User $user): bool
    {
        return $user->username === config('automator.protected_admin_username');
    }

    public function isSelf(User $user): bool
    {
        return $user->id === Auth::id();
    }

    public function startAdding(): void
    {
        $this->authorize('users.manage');

        $this->reset(['editingId', 'username', 'email', 'password', 'role']);
        $this->isAdding = true;
    }

    public function editUser(int $id): void
    {
        $this->authorize('users.manage');

        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? '';
        $this->isAdding = false;
    }

    public function cancelForm(): void
    {
        $this->reset(['editingId', 'isAdding', 'username', 'email', 'password', 'role']);
    }

    public function save(): void
    {
        $this->authorize('users.manage');

        $editing = $this->editingId ? User::findOrFail($this->editingId) : null;
        $locked = $editing && ($this->isProtected($editing) || $this->isSelf($editing));

        $rules = [
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($editing?->id)],
            'password' => [$editing ? 'nullable' : 'required', 'min:8'],
        ];

        if (! $locked) {
            $rules['username'] = ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($editing?->id)];
            $rules['role'] = ['required', Rule::in($this->roles)];
        }

        $validated = $this->validate($rules);

        if ($editing) {
            $editing->email = $validated['email'];
            if (filled($validated['password'])) {
                $editing->password = $validated['password'];
            }
            if (! $locked) {
                $editing->username = $validated['username'];
            }
            $editing->save();

            if (! $locked) {
                $editing->syncRoles([$validated['role']]);
            }

            AuditLog::record('User.Updated', $editing->username, 'profile updated');
        } else {
            $user = User::create([
                'username' => $validated['username'],
                'name' => ucfirst($validated['username']),
                'email' => $validated['email'],
                'password' => $validated['password'],
                'email_verified_at' => now(),
            ]);
            $user->assignRole($validated['role']);

            AuditLog::record('User.Created', $user->username, "role: {$validated['role']}");
        }

        $this->cancelForm();
        unset($this->users);
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $this->authorize('users.manage');

        $user = User::findOrFail($this->confirmingDeleteId);

        if ($this->isProtected($user) || $this->isSelf($user)) {
            $this->confirmingDeleteId = null;

            return;
        }

        $username = $user->username;
        $user->delete();

        AuditLog::record('User.Deleted', $username);

        $this->confirmingDeleteId = null;
        unset($this->users);
    }

    public function render()
    {
        return view('livewire.settings.user-management');
    }
}
