<div class="space-y-5">
    <div class="card card-pad">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-52 flex-1 sm:max-w-sm">
                <label for="reader-surah" class="label">اختر السورة</label>
                <select id="reader-surah" wire:model.live="surah" class="select">
                    @foreach ($surahs as $number => $name)
                        <option value="{{ $number }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="badge badge-neutral">{{ number_format($this->progress['total']) }} آية</span>
                <span class="badge badge-success">{{ number_format($this->progress['recorded']) }} مسجّلة</span>
                <a href="{{ route('student.recite', ['surah' => (int) $surah]) }}" class="btn btn-primary btn-sm">
                    <x-icon name="mic" class="h-4 w-4" />
                    تسجيل هذه السورة
                </a>
            </div>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-4">
            <div class="flex items-center justify-between text-xs font-bold">
                <span class="text-slate-500">تقدّمك في {{ $surahName }}</span>
                <span class="tabular text-mihrab-700">{{ $this->progress['percent'] }}%</span>
            </div>
            <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-sand-200">
                <div class="h-full rounded-full bg-gradient-to-l from-mihrab-600 to-mihrab-400 transition-all duration-500"
                     style="width: {{ $this->progress['percent'] }}%"></div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->ayahs as $ayah)
            <article wire:key="reader-ayah-{{ $ayah->id }}" class="card flex flex-col overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 bg-sand-50/80 px-4 py-2.5">
                    <div class="flex items-center gap-2 text-[11px] font-bold text-slate-500">
                        <span class="badge badge-neutral tabular" title="معرّف الآية">{{ $ayah->code }}</span>
                        <span>{{ $ayah->surah_name }} · {{ $ayah->ayah_number }}</span>
                    </div>

                    @php $status = $this->recordings[$ayah->id] ?? null; @endphp

                    @if ($status === \App\Models\Recording::STATUS_APPROVED)
                        <span class="badge badge-success">معتمدة</span>
                    @elseif ($status === \App\Models\Recording::STATUS_PENDING)
                        <span class="badge badge-warning">قيد المراجعة</span>
                    @elseif ($status === \App\Models\Recording::STATUS_REJECTED)
                        <span class="badge badge-danger">مرفوضة</span>
                    @else
                        <span class="badge badge-neutral">لم تُسجّل</span>
                    @endif
                </div>

                <div class="ayah-surface flex flex-1 items-center px-4 py-6">
                    <p class="ayah-text text-xl leading-loose text-slate-900">{{ $ayah->text_uthmani }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-4 py-2.5">
                    <a href="{{ route('student.recite', ['surah' => $ayah->surah_number]) }}"
                       class="flex items-center gap-1.5 text-[11px] font-bold text-mihrab-700 hover:text-mihrab-900">
                        <x-icon name="mic" class="h-3.5 w-3.5" />
                        الانتقال لتسجيل الآية
                    </a>

                    <button type="button" wire:click="report({{ $ayah->id }})"
                            wire:confirm="هل تريد إبلاغ المشرفين بأن نص هذه الآية أو تشكيلها غير صحيح؟"
                            class="flex items-center gap-1.5 text-[11px] font-bold text-rose-600 transition hover:text-rose-800">
                        <x-icon name="alert" class="h-3.5 w-3.5" />
                        الإبلاغ عن خطأ في الآية
                    </button>
                </div>
            </article>
        @empty
            <div class="sm:col-span-2 xl:col-span-3">
                <x-empty-state icon="book" title="لا توجد آيات لهذه السورة"
                               description="استورد ملف المصحف الكامل من لوحة الإدارة ليظهر هنا سرد الآيات آيةً آية.">
                    <x-slot:actions>
                        <a href="{{ route('student.recite') }}" class="btn btn-primary btn-sm">العودة إلى محراب التسجيل</a>
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @endforelse
    </div>

    <div>{{ $this->ayahs->links() }}</div>
</div>
