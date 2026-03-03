<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Roll Call')] class extends Component {
    public ClassRoom $classRoom;
    public string $selectedDate = '';
    public array $statusMap = [];

    public function mount(ClassRoom $classRoom): void
    {
        $this->authorize('view', $classRoom);
        $this->classRoom = $classRoom;
        $this->selectedDate = now()->toDateString();
        $this->loadStatusMap();
    }

    #[Computed]
    public function students(): Collection
    {
        return $this->classRoom->students()->orderBy('name')->get();
    }

    #[Computed]
    public function statuses(): array
    {
        return AttendanceStatus::cases();
    }

    public function updatedSelectedDate(): void
    {
        $this->loadStatusMap();
    }

    protected function loadStatusMap(): void
    {
        $existing = AttendanceRecord::where('class_room_id', $this->classRoom->id)
            ->whereDate('date', $this->selectedDate)
            ->get()
            ->keyBy('student_id');

        $this->statusMap = $this->students->mapWithKeys(function (User $student) use ($existing): array {
            return [
                $student->id => $existing->has($student->id)
                    ? $existing->get($student->id)->status->value
                    : AttendanceStatus::Present->value,
            ];
        })->toArray();
    }

    public function saveAttendance(): void
    {
        $this->authorize('create', AttendanceRecord::class);

        $now = now()->toDateTimeString();
        $records = [];

        foreach ($this->statusMap as $studentId => $status) {
            $records[] = [
                'class_room_id' => $this->classRoom->id,
                'student_id' => $studentId,
                'recorded_by' => Auth::id(),
                'date' => $this->selectedDate,
                'status' => $status,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($records)) {
            AttendanceRecord::upsert(
                $records,
                ['class_room_id', 'student_id', 'date'],
                ['status', 'recorded_by', 'updated_at']
            );
        }

        session()->flash('message', __('Attendance saved successfully.'));
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Roll Call') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ $classRoom->name }}</flux:text>
        </div>
        <flux:button href="{{ route('attendance.my-classes') }}" wire:navigate size="sm">
            {{ __('Back to Classes') }}
        </flux:button>
    </div>

    @if(session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <div class="flex items-center gap-4">
        <flux:input
            type="date"
            wire:model.live="selectedDate"
            :label="__('Date')"
            class="w-48"
        />
    </div>

    @if($this->students->isEmpty())
        <flux:text>{{ __('No students enrolled in this class.') }}</flux:text>
    @else
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Student') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->students as $student)
                        <tr class="bg-white dark:bg-zinc-900" wire:key="{{ $student->id }}">
                            <td class="px-4 py-3">
                                <flux:text>{{ $student->name }}</flux:text>
                                <flux:text class="text-xs text-zinc-400">{{ $student->email }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:select wire:model="statusMap.{{ $student->id }}" class="w-32">
                                    @foreach($this->statuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </flux:select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-end">
            <flux:button wire:click="saveAttendance" variant="primary">
                {{ __('Save Attendance') }}
            </flux:button>
        </div>
    @endif
</div>
