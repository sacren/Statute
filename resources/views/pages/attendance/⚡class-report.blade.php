<?php

use App\Concerns\CalculatesAttendancePercentage;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Class Report')] class extends Component {
    use CalculatesAttendancePercentage;

    public ClassRoom $classRoom;
    public int $year;
    public int $month;

    public function mount(ClassRoom $classRoom): void
    {
        $this->authorize('view', $classRoom);
        $this->classRoom = $classRoom;
        $this->year = now()->year;
        $this->month = now()->month;
    }

    #[Computed]
    public function studentsWithRecords(): Collection
    {
        return $this->classRoom->students()
            ->with(['attendanceRecords' => fn ($q) => $q->forMonth($this->year, $this->month)
                ->where('class_room_id', $this->classRoom->id)])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function alertThreshold(): float
    {
        return (float) config('attendance.alert_threshold');
    }

    #[Computed]
    public function monthLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function updatedYear(): void
    {
        unset($this->studentsWithRecords);
    }

    public function updatedMonth(): void
    {
        unset($this->studentsWithRecords);
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('view', $this->classRoom);

        $students = $this->studentsWithRecords;
        $classRoom = $this->classRoom;
        $year = $this->year;
        $month = $this->month;

        return response()->streamDownload(function () use ($students, $classRoom, $year, $month): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Class', 'Student', 'Email', 'Month', 'Present', 'Absent', 'Late', 'Excused', 'Attendance %']);

            $monthLabel = Carbon::create($year, $month, 1)->format('F Y');

            foreach ($students as $student) {
                $records = $student->attendanceRecords;
                $present = $records->where('status.value', 'present')->count();
                $absent = $records->where('status.value', 'absent')->count();
                $late = $records->where('status.value', 'late')->count();
                $excused = $records->where('status.value', 'excused')->count();
                $percentage = $this->calculatePercentage($records);

                fputcsv($handle, [
                    $classRoom->name,
                    $student->name,
                    $student->email,
                    $monthLabel,
                    $present,
                    $absent,
                    $late,
                    $excused,
                    number_format($percentage, 2).'%',
                ]);
            }

            fclose($handle);
        }, 'attendance-report-'.Carbon::create($year, $month, 1)->format('Y-m').'.csv');
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Class Report') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ $classRoom->name }}</flux:text>
        </div>
        <flux:button href="{{ route('attendance.my-classes') }}" wire:navigate size="sm">
            {{ __('Back to Classes') }}
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
        <flux:button wire:click="exportCsv" size="sm" icon="arrow-down-tray">
            {{ __('Export CSV') }}
        </flux:button>
        <flux:button disabled size="sm" icon="document" title="{{ __('PDF export requires the barryvdh/laravel-dompdf package') }}">
            {{ __('Export PDF') }}
        </flux:button>
    </div>

    <flux:heading size="lg">{{ $this->monthLabel }}</flux:heading>

    @if($this->studentsWithRecords->isEmpty())
        <flux:text>{{ __('No students enrolled in this class.') }}</flux:text>
    @else
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Student') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Present') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Late') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Absent') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Excused') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ __('Attendance %') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($this->studentsWithRecords as $student)
                        @php
                            $records = $student->attendanceRecords;
                            $percentage = $this->calculatePercentage($records);
                            $isBelowThreshold = $this->isBelowThreshold($records, $this->alertThreshold);
                        @endphp
                        <tr class="bg-white dark:bg-zinc-900" wire:key="{{ $student->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <flux:text class="font-medium">{{ $student->name }}</flux:text>
                                    @if($isBelowThreshold)
                                        <flux:badge color="red" size="sm">{{ __('Below Threshold') }}</flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ $records->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Present)->count() }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ $records->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Late)->count() }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ $records->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Absent)->count() }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ $records->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Excused)->count() }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge :color="$isBelowThreshold ? 'red' : 'green'">
                                    {{ number_format($percentage, 1) }}%
                                </flux:badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($this->studentsWithRecords->filter(fn($s) => $this->isBelowThreshold($s->attendanceRecords, $this->alertThreshold))->isNotEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ __(':count student(s) are below the :threshold% attendance threshold.', [
                    'count' => $this->studentsWithRecords->filter(fn($s) => $this->isBelowThreshold($s->attendanceRecords, $this->alertThreshold))->count(),
                    'threshold' => $this->alertThreshold,
                ]) }}
            </flux:callout>
        @endif
    @endif
</div>
