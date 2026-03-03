<?php

use App\Models\ClassRoom;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My Classes')] class extends Component {
    public function mount(): void
    {
        $this->authorize('viewAny', ClassRoom::class);
    }

    #[Computed]
    public function classRooms(): Collection
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return ClassRoom::with(['teachers', 'students'])->get();
        }

        return $user->taughtClassRooms()->with('students')->get();
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <flux:heading size="xl">{{ __('My Classes') }}</flux:heading>

    @if($this->classRooms->isEmpty())
        <flux:text>{{ __('No classes found.') }}</flux:text>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach($this->classRooms as $classRoom)
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="lg">{{ $classRoom->name }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">
                                {{ $classRoom->academic_year }}
                                @if($classRoom->grade_level)
                                    &middot; Grade {{ $classRoom->grade_level }}
                                @endif
                            </flux:text>
                        </div>
                        <flux:badge :color="$classRoom->is_active ? 'green' : 'zinc'">
                            {{ $classRoom->is_active ? __('Active') : __('Inactive') }}
                        </flux:badge>
                    </div>

                    <flux:text class="mt-2 text-sm">
                        {{ $classRoom->students->count() }} {{ __('students') }}
                    </flux:text>

                    <div class="mt-4 flex gap-2">
                        <flux:button
                            href="{{ route('attendance.roll-call', $classRoom) }}"
                            wire:navigate
                            variant="primary"
                            size="sm"
                        >
                            {{ __('Roll Call') }}
                        </flux:button>
                        <flux:button
                            href="{{ route('attendance.class-report', $classRoom) }}"
                            wire:navigate
                            size="sm"
                        >
                            {{ __('Report') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
