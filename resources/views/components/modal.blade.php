@props(['show' => false, 'title' => null, 'maxWidth' => 'max-w-lg', 'close' => null])

@if ($show)
    <div class="fixed inset-0 z-[70] flex min-h-full items-end justify-center overflow-y-auto sm:items-center sm:p-6" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm"
             @if ($close) wire:click="{{ $close }}" @endif></div>

        <div class="relative w-full {{ $maxWidth }} animate-card-in rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                <div class="min-w-0">
                    <h2 class="truncate text-base font-black text-slate-900">{{ $title }}</h2>
                    @isset($description)
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                    @endisset
                </div>

                @if ($close)
                    <button type="button" wire:click="{{ $close }}" class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="إغلاق">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                @endif
            </div>

            <div class="px-5 py-5">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4 sm:rounded-b-3xl">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
@endif
