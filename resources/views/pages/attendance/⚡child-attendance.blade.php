<?php

use App\Concerns\CalculatesAttendancePercentage;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Child Attendance')] class extends Component {
    use CalculatesAttendancePercentage;

    public User $student;
    public int $year;
    public int $month;

    public function mount(User $student): void
    {
        abort_if(! Auth::user()->isParent(), 403);
        abort_unless(Auth::user()->children()->where('users.id', $student->id)->exists(), 403);

        $this->student = $student;
        $this->year = now()->year;
        $this->month = now()->month;
    }

    #[Computed]
    public function records(): Collection
    {
        return AttendanceRecord::where('student_id', $this->student->id)
            ->forMonth($this->year, $this->month)
            ->with('classRoom')
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function monthLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    #[Computed]
    public function attendancePercentage(): float
    {
        return $this->calculatePercentage($this->records);
    }

    public function updatedYear(): void
    {
        unset($this->records);
    }

    public function updatedMonth(): void
    {
        unset($this->records);
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $student->name }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __("Attendance Record") }}</flux:text>
        </div>
        <flux:button href="{{ route('attendance.my-children') }}" wire:navigate size="sm">
            {{ __('Back to My Children') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <flux:input
            type="number"
            wire:model.live="year"
            :label="__('Year')"
            min="2000"
            max="2099"
            class="w-28"
        />
        <flux:select wire:model.live="month" :label="__('Month')" class="w-36">
            @foreach(range(1, 12) as $m)
                <option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
            @endforeach
        </flux:select>
    </div>

    <div class="flex items-center gap-4">
        <flux:heading size="lg">{{ $this->monthLabel }}</flux:heading>
        <flux:badge :color="$this->attendancePercentage >= 80 ? 'green' : 'red'" size="lg">
            {{ number_format($this->attendancePercentage, 1) }}% {{ __('Attendance') }}
        </flux:badge>
    </div>

    @if($this->records->isEmpty())
        <flux:text>{{ __('No attendance records found for this month.') }}</flux:text>
    @else
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Class') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->records as $record)
                        <tr class="bg-white dark:bg-zinc-900" wire:key="{{ $record->id }}">
                            <td class="px-4 py-3">
                                <flux:text>{{ $record->date->format('D, M j') }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $record->classRoom->name }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge :color="$record->status->color()">
                                    {{ $record->status->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text class="text-zinc-500">{{ $record->notes ?? '—' }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
