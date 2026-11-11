@props(['items' => [], 'pending' => 0])

<nav class="space-y-1.5">
    @foreach ($items as $item)
        <a href="{{ route($item['route']) }}"
           @class([
               'flex items-center gap-3 rounded-2xl px-3.5 py-2.5 text-sm font-bold transition',
               'bg-white text-mihrab-900 shadow-lg shadow-black/20' => $item['active'],
               'text-white/70 hover:bg-white/10 hover:text-white' => ! $item['active'],
           ])
           @if ($item['active']) aria-current="page" @endif>
            <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
            <span class="flex-1">{{ $item['label'] }}</span>
            @if ($item['route'] === 'teacher.queue' && $pending > 0)
                <span class="rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-black tabular text-white">{{ $pending }}</span>
            @endif
        </a>
    @endforeach
</nav>
