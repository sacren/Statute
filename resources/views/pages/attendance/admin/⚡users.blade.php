<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage Users')] class extends Component {
    public string $filterRole = '';
    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showLinkModal = false;
    public ?int $editingUserId = null;
    public ?int $linkingUserId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'student';
    public ?int $selectedChildId = null;

    public function mount(): void
    {
        abort_if(! Auth::user()->isAdmin(), 403);
    }

    #[Computed]
    public function users(): Collection
    {
        return User::query()
            ->when($this->filterRole, fn ($q) => $q->where('role', $this->filterRole))
            ->when($this->search, fn ($q) => $q->where(function ($q): void {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableStudents(): Collection
    {
        return User::role(UserRole::Student)->orderBy('name')->get();
    }

    #[Computed]
    public function roles(): array
    {
        return UserRole::cases();
    }

    public function openCreateModal(): void
    {
        $this->reset(['name', 'email', 'password', 'role']);
        $this->role = 'student';
        $this->showCreateModal = true;
    }

    public function createUser(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,teacher,student,parent'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'email_verified_at' => now(),
        ]);

        unset($this->users);
        $this->showCreateModal = false;
        session()->flash('message', __('User created successfully.'));
    }

    public function openEditModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingUserId = $userId;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->role->value;
        $this->showEditModal = true;
    }

    public function updateUser(): void
    {
        $user = User::findOrFail($this->editingUserId);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,teacher,student,parent'],
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        unset($this->users);
        $this->showEditModal = false;
        session()->flash('message', __('User updated successfully.'));
    }

    public function deleteUser(int $userId): void
    {
        abort_if($userId === Auth::id(), 403);
        User::findOrFail($userId)->delete();
        unset($this->users);
        session()->flash('message', __('User deleted successfully.'));
    }

    public function openLinkModal(int $parentId): void
    {
        $this->linkingUserId = $parentId;
        $this->selectedChildId = null;
        $this->showLinkModal = true;
    }

    public function linkChild(): void
    {
        $this->validate(['selectedChildId' => ['required', 'integer', 'exists:users,id']]);
        $parent = User::findOrFail($this->linkingUserId);
        $parent->children()->syncWithoutDetaching([$this->selectedChildId]);
        $this->showLinkModal = false;
        session()->flash('message', __('Child linked successfully.'));
    }

    public function unlinkChild(int $parentId, int $childId): void
    {
        User::findOrFail($parentId)->children()->detach($childId);
        unset($this->users);
        session()->flash('message', __('Child unlinked successfully.'));
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Manage Users') }}</flux:heading>
        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
            {{ __('New User') }}
        </flux:button>
    </div>

    @if(session('message'))
        <flux:callout variant="success" icon="check-circle">{{ session('message') }}</flux:callout>
    @endif

    <div class="flex flex-wrap gap-4">
        <flux:input wire:model.live="search" :placeholder="__('Search name or email…')" class="w-56" icon="magnifying-glass" />
        <flux:select wire:model.live="filterRole" class="w-36">
            <option value="">{{ __('All Roles') }}</option>
            @foreach($this->roles as $roleOption)
                <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
            @endforeach
        </flux:select>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Email') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Role') }}</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-700 dark:text-zinc-300">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->users as $user)
                    <tr class="bg-white dark:bg-zinc-900" wire:key="{{ $user->id }}">
                        <td class="px-4 py-3"><flux:text class="font-medium">{{ $user->name }}</flux:text></td>
                        <td class="px-4 py-3"><flux:text>{{ $user->email }}</flux:text></td>
                        <td class="px-4 py-3">
                            <flux:badge color="zinc">{{ $user->role->label() }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                @if($user->isParent())
                                    <flux:button wire:click="openLinkModal({{ $user->id }})" size="sm">
                                        {{ __('Link Child') }}
                                    </flux:button>
                                @endif
                                <flux:button wire:click="openEditModal({{ $user->id }})" size="sm">
                                    {{ __('Edit') }}
                                </flux:button>
                                @if($user->id !== auth()->id())
                                    <flux:button wire:click="deleteUser({{ $user->id }})" wire:confirm="{{ __('Are you sure you want to delete this user?') }}" size="sm" variant="danger">
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-400">{{ __('No users found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:w-96">
        <div class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Create User') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <flux:input wire:model="password" :label="__('Password')" type="password" required />
            <flux:select wire:model="role" :label="__('Role')">
                @foreach($this->roles as $roleOption)
                    <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="createUser" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:w-96">
        <div class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Edit User') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <flux:input wire:model="password" :label="__('New Password (leave blank to keep current)')" type="password" />
            <flux:select wire:model="role" :label="__('Role')">
                @foreach($this->roles as $roleOption)
                    <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="updateUser" variant="primary">{{ __('Update') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Link Child Modal --}}
    <flux:modal wire:model="showLinkModal" class="md:w-80">
        <div class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Link Child to Parent') }}</flux:heading>
            <flux:select wire:model="selectedChildId" :label="__('Select Student')">
                <option value="">{{ __('— Select a student —') }}</option>
                @foreach($this->availableStudents as $student)
                    <option value="{{ $student->id }}">{{ $student->name }}</option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showLinkModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="linkChild" variant="primary">{{ __('Link') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
