@props(['src', 'label' => null])

<div x-data="audioPlayer(@js($src))" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
    @if ($label)
        <p class="mb-2 text-[11px] font-bold text-slate-500">{{ $label }}</p>
    @endif

    <div class="flex items-center gap-3">
        <button type="button"
                @click="toggle()"
                :disabled="loading || !!error"
                :aria-label="playing ? 'إيقاف مؤقت' : 'تشغيل'"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-mihrab-700 text-white shadow-md shadow-mihrab-900/20 transition hover:bg-mihrab-800 disabled:opacity-50">
            <x-icon name="play" class="h-4 w-4" x-show="!playing" />
            <x-icon name="pause" class="h-4 w-4" x-show="playing" x-cloak />
        </button>

        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2 text-[11px] font-bold text-slate-500">
                <span class="tabular" x-text="currentLabel">0:00</span>
                <span x-show="loading" x-cloak>جارٍ التحميل…</span>
                <span class="tabular" x-text="durationLabel">0:00</span>
            </div>

            <input type="range" min="0" step="0.1"
                   :max="duration || 0"
                   :value="current"
                   :disabled="loading || !!error"
                   @input="seek($event)"
                   class="audio-range mt-1.5"
                   aria-label="موضع التشغيل">
        </div>

        <button type="button" @click="cycleSpeed()"
                class="shrink-0 rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-black tabular text-slate-600 transition hover:border-mihrab-300 hover:text-mihrab-800"
                aria-label="سرعة التشغيل">
            <span x-text="speed + '×'">1×</span>
        </button>
    </div>

    <p x-show="error" x-cloak x-text="error" class="mt-2 text-xs font-semibold text-rose-600"></p>
</div>
