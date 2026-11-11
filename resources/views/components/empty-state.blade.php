@props(['icon' => 'info', 'title' => 'لا توجد بيانات', 'description' => null])

<div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-sand-300 bg-white/60 px-6 py-14 text-center">
    <span class="flex h-16 w-16 items-center justify-center rounded-3xl bg-mihrab-50 text-mihrab-700">
        <x-icon :name="$icon" class="h-7 w-7" />
    </span>

    <p class="mt-4 text-base font-black text-slate-800">{{ $title }}</p>

    @if ($description)
        <p class="mt-1.5 max-w-md text-sm leading-6 text-slate-500">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
