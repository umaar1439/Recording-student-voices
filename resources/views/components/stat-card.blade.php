@props(['label', 'value', 'icon' => 'chart', 'tone' => 'mihrab', 'hint' => null])

@php
    $tones = [
        'mihrab' => 'bg-mihrab-50 text-mihrab-700 ring-mihrab-100',
        'gold' => 'bg-gold-400/15 text-gold-600 ring-gold-400/30',
        'rose' => 'bg-rose-50 text-rose-600 ring-rose-100',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'amber' => 'bg-amber-50 text-amber-600 ring-amber-100',
    ];
@endphp

<div class="stat-card">
    <div class="flex items-start justify-between gap-3">
        <p class="stat-label">{{ $label }}</p>
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ring-1 {{ $tones[$tone] ?? $tones['mihrab'] }}">
            <x-icon :name="$icon" class="h-4.5 w-4.5" />
        </span>
    </div>

    <p class="stat-value mt-1">{{ $value }}</p>

    @if ($hint)
        <p class="text-[11px] font-semibold text-slate-400">{{ $hint }}</p>
    @endif
</div>
