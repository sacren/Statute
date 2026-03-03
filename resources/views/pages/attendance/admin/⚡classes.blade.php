<?php

use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage Classes')] class extends Component {
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $editingClassRoomId = null;

    public string $name = '';
    public string $description = '';
    public string $gradeLevel = '';
    public string $academicYear = '';
    public bool $isActive = true;
    public array $selectedTeacherIds = [];
    public array $selectedStudentIds = [];

    public function mount(): void
    {
        abort_if(! Auth::user()->isAdmin(), 403);
    }

    #[Computed]
    public function classRooms(): Collection
    {
        return ClassRoom::with(['teachers', 'students'])->latest()->get();
    }

    #[Computed]
    public function teachers(): Collection
    {
        return User::role(\App\Enums\UserRole::Teacher)->orderBy('name')->get();
    }

    #[Computed]
    public function students(): Collection
    {
        return User::role(\App\Enums\UserRole::Student)->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        $this->reset(['name', 'description', 'gradeLevel', 'academicYear', 'isActive', 'selectedTeacherIds', 'selectedStudentIds']);
        $this->isActive = true;
        $this->academicYear = now()->year.'-'.(now()->year + 1);
        $this->showCreateModal = true;
    }

    public function createClassRoom(): void
    {
        $this->authorize('create', ClassRoom::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'gradeLevel' => ['nullable', 'string', 'max:50'],
            'academicYear' => ['required', 'string', 'max:50'],
            'isActive' => ['boolean'],
            'selectedTeacherIds' => ['array'],
            'selectedStudentIds' => ['array'],
        ]);

        $classRoom = ClassRoom::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'grade_level' => $validated['gradeLevel'],
            'academic_year' => $validated['academicYear'],
            'is_active' => $validated['isActive'],
        ]);

        $classRoom->teachers()->sync($this->selectedTeacherIds);
        $classRoom->students()->sync(
            collect($this->selectedStudentIds)->mapWithKeys(fn ($id) => [$id => ['enrolled_at' => now()->toDateString()]])->toArray()
        );

        unset($this->classRooms);
        $this->showCreateModal = false;
        session()->flash('message', __('Class created successfully.'));
    }

    public function openEditModal(int $classRoomId): void
    {
        $classRoom = ClassRoom::with(['teachers', 'students'])->findOrFail($classRoomId);
        $this->authorize('update', $classRoom);

        $this->editingClassRoomId = $classRoomId;
        $this->name = $classRoom->name;
        $this->description = $classRoom->description ?? '';
        $this->gradeLevel = $classRoom->grade_level ?? '';
        $this->academicYear = $classRoom->academic_year;
        $this->isActive = $classRoom->is_active;
        $this->selectedTeacherIds = $classRoom->teachers->pluck('id')->toArray();
        $this->selectedStudentIds = $classRoom->students->pluck('id')->toArray();
        $this->showEditModal = true;
    }

    public function updateClassRoom(): void
    {
        $classRoom = ClassRoom::findOrFail($this->editingClassRoomId);
        $this->authorize('update', $classRoom);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'gradeLevel' => ['nullable', 'string', 'max:50'],
            'academicYear' => ['required', 'string', 'max:50'],
            'isActive' => ['boolean'],
            'selectedTeacherIds' => ['array'],
            'selectedStudentIds' => ['array'],
        ]);

        $classRoom->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'grade_level' => $validated['gradeLevel'],
            'academic_year' => $validated['academicYear'],
            'is_active' => $validated['isActive'],
        ]);

        $classRoom->teachers()->sync($this->selectedTeacherIds);
        $classRoom->students()->sync(
            collect($this->selectedStudentIds)->mapWithKeys(fn ($id) => [$id => ['enrolled_at' => now()->toDateString()]])->toArray()
        );

        unset($this->classRooms);
        $this->showEditModal = false;
        session()->flash('message', __('Class updated successfully.'));
    }

    public function deleteClassRoom(int $classRoomId): void
    {
        $classRoom = ClassRoom::findOrFail($classRoomId);
        $this->authorize('delete', $classRoom);
        $classRoom->delete();
        unset($this->classRooms);
        session()->flash('message', __('Class deleted successfully.'));
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Manage Classes') }}</flux:heading>
        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
            {{ __('New Class') }}
        </flux:button>
    </div>

    @if(session('message'))
        <flux:callout variant="success" icon="check-circle">{{ session('message') }}</flux:callout>
    @endif

    @if($this->classRooms->isEmpty())
        <flux:text>{{ __('No classes found. Create your first class.') }}</flux:text>
    @else
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Academic Year') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Teachers') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Students') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-700 dark:text-zinc-300">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->classRooms as $classRoom)
                        <tr class="bg-white dark:bg-zinc-900" wire:key="{{ $classRoom->id }}">
                            <td class="px-4 py-3">
                                <flux:text class="font-medium">{{ $classRoom->name }}</flux:text>
                                @if($classRoom->grade_level)
                                    <flux:text class="text-xs text-zinc-400">Grade {{ $classRoom->grade_level }}</flux:text>
                                @endif
                            </td>
                            <td class="px-4 py-3"><flux:text>{{ $classRoom->academic_year }}</flux:text></td>
                            <td class="px-4 py-3 text-center"><flux:text>{{ $classRoom->teachers->count() }}</flux:text></td>
                            <td class="px-4 py-3 text-center"><flux:text>{{ $classRoom->students->count() }}</flux:text></td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge :color="$classRoom->is_active ? 'green' : 'zinc'">
                                    {{ $classRoom->is_active ? __('Active') : __('Inactive') }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <flux:button wire:click="openEditModal({{ $classRoom->id }})" size="sm">
                                        {{ __('Edit') }}
                                    </flux:button>
                                    <flux:button wire:click="deleteClassRoom({{ $classRoom->id }})" wire:confirm="{{ __('Are you sure you want to delete this class?') }}" size="sm" variant="danger">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:w-96">
        <div class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Create Class') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:textarea wire:model="description" :label="__('Description (optional)')" rows="2" />
            <flux:input wire:model="gradeLevel" :label="__('Grade Level (optional)')" />
            <flux:input wire:model="academicYear" :label="__('Academic Year')" required />

            <div>
                <flux:label>{{ __('Teachers') }}</flux:label>
                <div class="mt-1 space-y-1 max-h-40 overflow-y-auto">
                    @foreach($this->teachers as $teacher)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedTeacherIds" value="{{ $teacher->id }}" class="rounded">
                            {{ $teacher->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <flux:label>{{ __('Students') }}</flux:label>
                <div class="mt-1 space-y-1 max-h-40 overflow-y-auto">
                    @foreach($this->students as $student)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedStudentIds" value="{{ $student->id }}" class="rounded">
                            {{ $student->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="createClassRoom" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:w-96">
        <div class="p-6 space-y-4">
            <flux:heading size="lg">{{ __('Edit Class') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:textarea wire:model="description" :label="__('Description (optional)')" rows="2" />
            <flux:input wire:model="gradeLevel" :label="__('Grade Level (optional)')" />
            <flux:input wire:model="academicYear" :label="__('Academic Year')" required />
            <flux:checkbox wire:model="isActive" :label="__('Active')" />

            <div>
                <flux:label>{{ __('Teachers') }}</flux:label>
                <div class="mt-1 space-y-1 max-h-40 overflow-y-auto">
                    @foreach($this->teachers as $teacher)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedTeacherIds" value="{{ $teacher->id }}" class="rounded">
                            {{ $teacher->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <flux:label>{{ __('Students') }}</flux:label>
                <div class="mt-1 space-y-1 max-h-40 overflow-y-auto">
                    @foreach($this->students as $student)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedStudentIds" value="{{ $student->id }}" class="rounded">
                            {{ $student->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="updateClassRoom" variant="primary">{{ __('Update') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
