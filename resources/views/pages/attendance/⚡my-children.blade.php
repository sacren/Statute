<?php

use App\Concerns\CalculatesAttendancePercentage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My Children')] class extends Component {
    use CalculatesAttendancePercentage;

    public function mount(): void
    {
        abort_if(! Auth::user()->isParent(), 403);
    }

    #[Computed]
    public function children(): Collection
    {
        return Auth::user()->children()
            ->with(['attendanceRecords' => fn ($q) => $q->forMonth(now()->year, now()->month)])
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <flux:heading size="xl">{{ __('My Children') }}</flux:heading>

    @if($this->children->isEmpty())
        <flux:text>{{ __('No children linked to your account.') }}</flux:text>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach($this->children as $child)
                @php
                    $percentage = $this->calculatePercentage($child->attendanceRecords);
                @endphp
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="lg">{{ $child->name }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">{{ $child->email }}</flux:text>
                        </div>
                        <flux:badge :color="$percentage >= 80 ? 'green' : 'red'">
                            {{ number_format($percentage, 1) }}%
                        </flux:badge>
                    </div>

                    <flux:text class="mt-2 text-xs text-zinc-400">
                        {{ __('This month\'s attendance') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button
                            href="{{ route('attendance.child-attendance', $child) }}"
                            wire:navigate
                            variant="primary"
                            size="sm"
                        >
                            {{ __('View Details') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
