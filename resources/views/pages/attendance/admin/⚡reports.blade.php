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
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Reports')] class extends Component {
    use CalculatesAttendancePercentage;

    public int $year;
    public int $month;

    public function mount(): void
    {
        abort_if(! Auth::user()->isAdmin(), 403);
        $this->year = now()->year;
        $this->month = now()->month;
    }

    #[Computed]
    public function classRoomsWithData(): Collection
    {
        return ClassRoom::with([
            'teachers',
            'students.attendanceRecords' => fn ($q) => $q->forMonth($this->year, $this->month),
        ])->get();
    }

    #[Computed]
    public function monthLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function updatedYear(): void
    {
        unset($this->classRoomsWithData);
    }

    public function updatedMonth(): void
    {
        unset($this->classRoomsWithData);
    }

    public function exportCsv(): StreamedResponse
    {
        abort_if(! Auth::user()->isAdmin(), 403);

        $classRooms = $this->classRoomsWithData;
        $year = $this->year;
        $month = $this->month;

        return response()->streamDownload(function () use ($classRooms, $year, $month): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Class', 'Student', 'Email', 'Month', 'Present', 'Absent', 'Late', 'Excused', 'Attendance %']);

            $monthLabel = Carbon::create($year, $month, 1)->format('F Y');

            foreach ($classRooms as $classRoom) {
                foreach ($classRoom->students as $student) {
                    $records = $student->attendanceRecords;
                    $present = $records->filter(fn ($r) => $r->status === \App\Enums\AttendanceStatus::Present)->count();
                    $absent = $records->filter(fn ($r) => $r->status === \App\Enums\AttendanceStatus::Absent)->count();
                    $late = $records->filter(fn ($r) => $r->status === \App\Enums\AttendanceStatus::Late)->count();
                    $excused = $records->filter(fn ($r) => $r->status === \App\Enums\AttendanceStatus::Excused)->count();
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
            }

            fclose($handle);
        }, 'aggregate-report-'.Carbon::create($year, $month, 1)->format('Y-m').'.csv');
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <flux:heading size="xl">{{ __('Aggregate Reports') }}</flux:heading>

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
            {{ __('Export All CSV') }}
        </flux:button>
        <flux:button disabled size="sm" icon="document" title="{{ __('PDF export requires the barryvdh/laravel-dompdf package') }}">
            {{ __('Export PDF') }}
        </flux:button>
    </div>

    <flux:heading size="lg">{{ $this->monthLabel }}</flux:heading>

    @forelse($this->classRoomsWithData as $classRoom)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-50 dark:bg-zinc-800 px-4 py-3 flex items-center justify-between">
                <flux:heading size="md">{{ $classRoom->name }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    {{ $classRoom->teachers->pluck('name')->join(', ') }}
                </flux:text>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Student') }}</th>
                        <th class="px-4 py-2 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Present') }}</th>
                        <th class="px-4 py-2 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Late') }}</th>
                        <th class="px-4 py-2 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Absent') }}</th>
                        <th class="px-4 py-2 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Excused') }}</th>
                        <th class="px-4 py-2 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Attendance %') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($classRoom->students as $student)
                        @php
                            $records = $student->attendanceRecords;
                            $percentage = $this->calculatePercentage($records);
                        @endphp
                        <tr class="bg-white dark:bg-zinc-900" wire:key="{{ $classRoom->id }}-{{ $student->id }}">
                            <td class="px-4 py-3"><flux:text>{{ $student->name }}</flux:text></td>
                            <td class="px-4 py-3 text-center">{{ $records->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Present)->count() }}</td>
                            <td class="px-4 py-3 text-center">{{ $records->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Late)->count() }}</td>
                            <td class="px-4 py-3 text-center">{{ $records->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Absent)->count() }}</td>
                            <td class="px-4 py-3 text-center">{{ $records->filter(fn($r) => $r->status === \App\Enums\AttendanceStatus::Excused)->count() }}</td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge :color="$percentage >= 80 ? 'green' : 'red'">
                                    {{ number_format($percentage, 1) }}%
                                </flux:badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-zinc-400 text-sm">{{ __('No students enrolled.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <flux:text>{{ __('No classes found.') }}</flux:text>
    @endforelse
</div>
