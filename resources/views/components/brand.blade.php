@props(['variant' => 'light', 'href' => null])

@php
    $dark = $variant === 'dark';
@endphp

<a href="{{ $href ?? (auth()->check() ? route('dashboard') : route('home')) }}" class="group flex items-center gap-3">
    <span @class([
        'relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl shadow-lg transition group-hover:scale-105',
        'bg-gradient-to-br from-mihrab-700 to-mihrab-900 text-gold-400 shadow-mihrab-950/40' => $dark,
        'bg-mihrab-800 text-gold-400 shadow-mihrab-900/25' => ! $dark,
    ])>
        <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
            <path d="M4.5 21V10.5a7.5 7.5 0 0 1 15 0V21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            <path d="M8.25 21v-8.25m7.5 8.25v-8.25" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".55" />
            <path d="M12 7.5v5.25m0 0 2.25-1.5M12 12.75l-2.25-1.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M3 21h18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>
    </span>

    <span class="min-w-0">
        <span @class([
            'block text-[15px] font-black leading-tight',
            'text-white' => $dark,
            'text-slate-900' => ! $dark,
        ])>مِحراب التلاوة</span>
        <span @class([
            'mt-0.5 block text-[10.5px] font-semibold leading-tight',
            'text-white/50' => $dark,
            'text-slate-500' => ! $dark,
        ])>مجموعة بيانات التلاوات القرآنية</span>
    </span>
</a>
