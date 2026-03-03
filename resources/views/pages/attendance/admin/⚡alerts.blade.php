<?php

use App\Concerns\CalculatesAttendancePercentage;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Attendance Alerts')] class extends Component {
    use CalculatesAttendancePercentage;

    public int $year;
    public int $month;
    public float $threshold;

    public function mount(): void
    {
        abort_if(! Auth::user()->isAdmin(), 403);
        $this->year = now()->year;
        $this->month = now()->month;
        $this->threshold = (float) config('attendance.alert_threshold');
    }

    #[Computed]
    public function flaggedStudents(): \Illuminate\Support\Collection
    {
        $classRooms = ClassRoom::with([
            'students.attendanceRecords' => fn ($q) => $q->forMonth($this->year, $this->month),
        ])->get();

        $flagged = collect();

        foreach ($classRooms as $classRoom) {
            foreach ($classRoom->students as $student) {
                $records = $student->attendanceRecords;

                if ($this->isBelowThreshold($records, $this->threshold)) {
                    $flagged->push([
                        'student' => $student,
                        'classRoom' => $classRoom,
                        'records' => $records,
                        'percentage' => $this->calculatePercentage($records),
                    ]);
                }
            }
        }

        return $flagged->sortBy('percentage');
    }

    #[Computed]
    public function monthLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function updatedYear(): void
    {
        unset($this->flaggedStudents);
    }

    public function updatedMonth(): void
    {
        unset($this->flaggedStudents);
    }

    public function updatedThreshold(): void
    {
        unset($this->flaggedStudents);
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <flux:heading size="xl">{{ __('Attendance Alerts') }}</flux:heading>

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
        <flux:input
            type="number"
            wire:model.live="threshold"
            :label="__('Alert Threshold (%)')"
            min="0"
            max="100"
            step="1"
            class="w-28"
        />
    </div>

    <div class="flex items-center gap-3">
        <flux:heading size="lg">{{ $this->monthLabel }}</flux:heading>
        <flux:badge :color="$this->flaggedStudents->isEmpty() ? 'green' : 'red'" size="lg">
            {{ $this->flaggedStudents->count() }} {{ __('flagged') }}
        </flux:badge>
    </div>

    @if($this->flaggedStudents->isEmpty())
        <flux:callout variant="success" icon="check-circle">
            {{ __('No students are below the :threshold% attendance threshold for this month.', ['threshold' => $this->threshold]) }}
        </flux:callout>
    @else
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __(':count student(s) are below the :threshold% attendance threshold.', [
                'count' => $this->flaggedStudents->count(),
                'threshold' => $this->threshold,
            ]) }}
        </flux:callout>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Student') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Class') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Attendance %') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Absent') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Excused') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->flaggedStudents as $item)
                        <tr class="bg-white dark:bg-zinc-900" wire:key="{{ $item['student']->id }}-{{ $item['classRoom']->id }}">
                            <td class="px-4 py-3">
                                <flux:text class="font-medium">{{ $item['student']->name }}</flux:text>
                                <flux:text class="text-xs text-zinc-400">{{ $item['student']->email }}</flux:text>
                            </td>
                            <td class="px-4 py-3"><flux:text>{{ $item['classRoom']->name }}</flux:text></td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge color="red">
                                    {{ number_format($item['percentage'], 1) }}%
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ $item['records']->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Absent)->count() }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ $item['records']->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Excused)->count() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
