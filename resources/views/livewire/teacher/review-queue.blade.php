@php
    $statusTabs = [
        'pending' => ['label' => 'بانتظار المراجعة', 'count' => $this->counts['pending']],
        'approved' => ['label' => 'المعتمدة', 'count' => $this->counts['approved']],
        'rejected' => ['label' => 'المرفوضة', 'count' => $this->counts['rejected']],
        'all' => ['label' => 'الكل', 'count' => $this->counts['pending'] + $this->counts['approved'] + $this->counts['rejected']],
    ];
@endphp

<div class="space-y-5">
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
            <nav class="flex flex-wrap gap-1.5" aria-label="تصفية الحالة">
                @foreach ($statusTabs as $value => $meta)
                    <button type="button" wire:click="setTab('{{ $value }}')"
                            class="chip {{ $tab === $value ? 'chip-active' : '' }}">
                        {{ $meta['label'] }}
                        <span class="tabular {{ $tab === $value ? 'text-white/80' : 'text-slate-400' }}">({{ $meta['count'] }})</span>
                    </button>
                @endforeach
            </nav>

            <div class="flex flex-wrap items-center gap-2">
                <span class="badge badge-danger">{{ $this->counts['wrong'] }} خاطئة معلّقة</span>
                <span class="badge badge-success">{{ $this->counts['correct'] }} صحيحة معلّقة</span>
            </div>
        </div>

        <div class="grid gap-3 px-4 py-4 sm:px-5 md:grid-cols-3">
            <div>
                <label for="queue-search" class="label">بحث</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="queue-search" type="search" wire:model.live.debounce.400ms="search"
                           class="input pr-9" placeholder="اسم الطالب أو نص الآية…">
                </div>
            </div>

            <div>
                <label for="queue-surah" class="label">السورة</label>
                <select id="queue-surah" wire:model.live="surah" class="select">
                    <option value="all">كل السور</option>
                    @foreach ($surahs as $number => $name)
                        <option value="{{ $number }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <span class="label">نوع التلاوة</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (['all' => 'الكل', 'correct' => 'صحيحة', 'wrong' => 'خاطئة'] as $value => $label)
                        <button type="button" wire:click="$set('type', '{{ $value }}')"
                                class="chip {{ $type === $value ? 'chip-active' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($search !== '' || $surah !== 'all' || $type !== 'all')
            <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/60 px-4 py-2.5 sm:px-5">
                <p class="text-xs font-semibold text-slate-500">فلاتر مطبّقة على {{ $this->recordings->total() }} نتيجة.</p>
                <button type="button" wire:click="clearFilters" class="text-xs font-black text-mihrab-700 hover:text-mihrab-900">
                    مسح الفلاتر
                </button>
            </div>
        @endif
    </div>

    @if ($this->recordings->isEmpty())
        <x-empty-state icon="inbox" title="لا توجد تسجيلات في هذه القائمة"
                       description="لم يتم العثور على تسجيلات مطابقة للتصفية الحالية. جرّب تبويبًا آخر أو امسح الفلاتر.">
            <x-slot:actions>
                <button type="button" wire:click="clearFilters" class="btn btn-secondary btn-sm">مسح الفلاتر</button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="space-y-3">
            @foreach ($this->recordings as $recording)
                <article wire:key="recording-{{ $recording->id }}" class="card card-pad">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-mihrab-50 text-xs font-black text-mihrab-800">
                                {{ $recording->user?->initials() ?? '؟' }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-900">{{ $recording->user?->name ?? 'طالب محذوف' }}</p>
                                <p class="truncate text-[11px] font-semibold text-slate-400">{{ $recording->user?->email }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge {{ $recording->type === 'correct' ? 'badge-success' : 'badge-danger' }}">
                                {{ $recording->typeLabel() }}
                            </span>
                            @include('student.partials.status-badge', ['recording' => $recording])
                            <span class="badge badge-neutral tabular">{{ $recording->durationLabel() }}</span>
                        </div>
                    </div>

                    <div class="mt-3 rounded-2xl px-4 py-3 {{ $recording->type === 'wrong' ? 'bg-rose-50' : 'bg-emerald-50/70' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[11px] font-bold {{ $recording->type === 'wrong' ? 'text-rose-800' : 'text-emerald-800' }}">{{ $recording->ayah->reference }}</p>
                            <span class="badge badge-neutral tabular" title="معرّف المهمة">{{ $recording->taskCode() }}</span>
                        </div>
                        <p class="ayah-text mt-1.5 line-clamp-3 text-right text-lg leading-loose {{ $recording->type === 'wrong' ? 'text-rose-800' : 'text-emerald-900' }}">
                            {{ $recording->displayText() }}
                        </p>
                    </div>

                    <div class="mt-3">
                        <x-audio-player :src="$recording->audioUrl()" label="تسجيل الطالب" />
                    </div>

                    @if ($recording->review_notes)
                        <p class="mt-3 rounded-xl border border-rose-100 bg-rose-50/70 px-3 py-2 text-xs font-semibold leading-6 text-rose-800">
                            ملاحظة المراجعة: {{ $recording->review_notes }}
                        </p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                        <p class="text-[11px] font-semibold text-slate-400">
                            أُرسل {{ $recording->created_at->diffForHumans() }}
                            @if ($recording->reviewer)
                                · راجعه {{ $recording->reviewer->name }}
                            @endif
                        </p>

                        @if ($recording->isPending())
                            <div class="flex gap-2">
                                <button type="button" wire:click="approve({{ $recording->id }})" wire:loading.attr="disabled"
                                        class="btn btn-primary btn-sm">
                                    <x-icon name="check" class="h-4 w-4" />
                                    اعتماد
                                </button>
                                <button type="button" wire:click="openReject({{ $recording->id }})" wire:loading.attr="disabled"
                                        class="btn btn-danger btn-sm">
                                    <x-icon name="x" class="h-4 w-4" />
                                    رفض
                                </button>
                            </div>
                        @else
                            <a href="{{ route('recordings.audio', $recording) }}" target="_blank" rel="noopener"
                               class="btn btn-ghost btn-sm">
                                <x-icon name="download" class="h-4 w-4" />
                                تحميل الملف
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div>{{ $this->recordings->links() }}</div>
    @endif

    <x-modal :show="$reviewingId !== null" title="رفض التسجيل" close="cancelReject"
         description="اكتب سبب الرفض لمساعدة الطالب على تصحيح تلاوته (اختياري).">
    <label for="reject-note" class="label">ملاحظة المراجعة</label>
    <textarea id="reject-note" wire:model="note" rows="4" maxlength="500"
              class="textarea" placeholder="مثال: خطأ في مخارج الحروف عند الكلمة الثالثة…"></textarea>
    @error('note')
        <p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>
    @enderror
    <p class="mt-1.5 text-[11px] font-semibold text-slate-400">{{ mb_strlen($note) }} / 500 حرف</p>

    <x-slot:footer>
        <button type="button" wire:click="cancelReject" class="btn btn-secondary btn-sm">إلغاء</button>
        <button type="button" wire:click="confirmReject" wire:loading.attr="disabled" class="btn btn-danger btn-sm">
            تأكيد الرفض
        </button>
    </x-slot:footer>
    </x-modal>
</div>
