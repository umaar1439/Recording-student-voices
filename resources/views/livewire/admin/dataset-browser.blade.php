@php $query = $this->exportQuery(); @endphp

<div class="space-y-5">
    <div class="card card-pad">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="dataset-student" class="label">الطالب</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                            <x-icon name="search" class="h-4 w-4" />
                        </span>
                        <input id="dataset-student" type="search" wire:model.live.debounce.400ms="student"
                               class="input pr-9" placeholder="الاسم أو البريد…">
                    </div>
                </div>

                <div>
                    <label for="dataset-surah" class="label">السورة</label>
                    <select id="dataset-surah" wire:model.live="surah" class="select">
                        <option value="all">كل السور</option>
                        @foreach ($surahs as $number => $name)
                            <option value="{{ $number }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="dataset-from" class="label">من تاريخ</label>
                    <input id="dataset-from" type="date" wire:model.live="from" class="input tabular">
                </div>

                <div>
                    <label for="dataset-to" class="label">إلى تاريخ</label>
                    <input id="dataset-to" type="date" wire:model.live="to" class="input tabular">
                </div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-black text-slate-400">النوع:</span>
                @foreach (['all' => 'الكل', 'correct' => 'صحيحة', 'wrong' => 'خاطئة'] as $value => $label)
                    <button type="button" wire:click="$set('type', '{{ $value }}')"
                            class="chip {{ $type === $value ? 'chip-active' : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if (count($query) > 0)
                <button type="button" wire:click="clearFilters" class="text-xs font-black text-mihrab-700 hover:text-mihrab-900">
                    مسح كل الفلاتر
                </button>
            @endif
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-pad">
            <p class="text-[11px] font-bold text-slate-500">تسجيلات معتمدة</p>
            <p class="mt-1 text-2xl font-black tabular text-slate-900">{{ number_format($this->stats['approved']) }}</p>
            <p class="mt-1 text-[10px] font-bold text-slate-400">{{ $this->stats['students'] }} طالب مشارك</p>
        </div>
        <div class="card card-pad">
            <p class="text-[11px] font-bold text-slate-500">تلاوات صحيحة</p>
            <p class="mt-1 text-2xl font-black tabular text-mihrab-700">{{ number_format($this->stats['correct']) }}</p>
            <p class="mt-1 text-[10px] font-bold text-slate-400">{{ $this->stats['approved'] > 0 ? round($this->stats['correct'] / $this->stats['approved'] * 100) : 0 }}% من المعتمد</p>
        </div>
        <div class="card card-pad">
            <p class="text-[11px] font-bold text-slate-500">تلاوات خاطئة</p>
            <p class="mt-1 text-2xl font-black tabular text-rose-600">{{ number_format($this->stats['wrong']) }}</p>
            <p class="mt-1 text-[10px] font-bold text-slate-400">لتدريب نماذج التصحيح</p>
        </div>
        <div class="card card-pad">
            <p class="text-[11px] font-bold text-slate-500">إجمالي المدة الصوتية</p>
            <p class="mt-1 text-2xl font-black tabular text-slate-900">{{ number_format(round($this->stats['duration'] / 60, 1)) }}<span class="text-sm"> دقيقة</span></p>
            <p class="mt-1 text-[10px] font-bold text-slate-400">{{ number_format($this->stats['duration']) }} ثانية</p>
        </div>
    </div>

    <section class="card card-pad">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-base font-black text-slate-900">
                    <x-icon name="download" class="h-4 w-4 text-mihrab-700" />
                    تصدير مجموعة البيانات
                </h2>
                <p class="mt-1 text-xs font-semibold leading-6 text-slate-500">
                    يتم تصدير التسجيلات المعتمدة فقط، مع احترام الفلاتر الحالية.
                    @if (count($query) > 0)
                        <span class="font-black text-mihrab-800">الفلاتر مطبّقة الآن.</span>
                    @else
                        <span class="font-black text-slate-700">بدون فلاتر: كل المعتمد.</span>
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.datasets.export', $query) }}" class="btn btn-primary btn-sm">
                    <x-icon name="download" class="h-4 w-4" />
                    حزمة ZIP (صوت + بيانات)
                </a>
                <a href="{{ route('admin.datasets.export.json', $query) }}" class="btn btn-secondary btn-sm">
                    <x-icon name="database" class="h-4 w-4" />
                    JSON
                </a>
                <a href="{{ route('admin.datasets.export.csv', $query) }}" class="btn btn-secondary btn-sm">
                    <x-icon name="chart" class="h-4 w-4" />
                    CSV
                </a>
            </div>
        </div>

        <div class="mt-4 grid gap-2 border-t border-slate-100 pt-4 text-[11px] font-semibold text-slate-500 sm:grid-cols-3">
            <p>◆ حزمة ZIP: ملفات WAV داخل audio/correct و audio/wrong مع manifest.json و dataset.csv.</p>
            <p>◆ المعرّفات: 001001 للتلاوة الصحيحة و001001x للخاطئة، مع النص الأصلي والنص المتلو.</p>
            <p>◆ ملف CSV مناسب لجداول البيانات، وJSON مناسب للتدريب البرمجي والنماذج.</p>
        </div>
    </section>

    @if ($this->recordings->isEmpty())
        <x-empty-state icon="database" title="لا توجد تسجيلات معتمدة مطابقة"
                       description="لم يتم اعتماد أي تسجيل يطابق الفلاتر الحالية. راجع التسجيلات المعلّقة أولًا أو عدّل الفلاتر.">
            <x-slot:actions>
                <a href="{{ route('teacher.queue') }}" class="btn btn-primary btn-sm">مراجعة التسجيلات المعلّقة</a>
                <button type="button" wire:click="clearFilters" class="btn btn-secondary btn-sm">مسح الفلاتر</button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
                <h2 class="text-sm font-black text-slate-900">سجلات المجموعة</h2>
                <p class="text-[11px] font-semibold text-slate-400">{{ number_format($this->stats['approved']) }} تسجيل معتمد</p>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>الطالب</th>
                            <th>الآية</th>
                            <th>النوع</th>
                            <th>المدة</th>
                            <th>تاريخ الاعتماد</th>
                            <th class="text-left">الملف</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->recordings as $recording)
                            <tr wire:key="dataset-{{ $recording->id }}">
                                <td>
                                    <p class="font-bold text-slate-900">{{ $recording->user?->name ?? '—' }}</p>
                                    <p class="text-[10px] font-semibold text-slate-400">{{ $recording->user?->email }}</p>
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <p class="whitespace-nowrap text-xs font-bold text-mihrab-800">{{ $recording->ayah->reference }}</p>
                                        <span class="badge badge-neutral tabular">{{ $recording->taskCode() }}</span>
                                    </div>
                                    <p class="mt-0.5 line-clamp-1 max-w-64 text-xs {{ $recording->type === 'wrong' ? 'text-rose-600' : 'text-slate-500' }}">
                                        {{ $recording->displayText() }}
                                    </p>
                                </td>
                                <td>
                                    <span class="badge {{ $recording->type === 'correct' ? 'badge-success' : 'badge-danger' }}">{{ $recording->typeLabel() }}</span>
                                </td>
                                <td class="text-xs font-semibold tabular text-slate-600">{{ $recording->durationLabel() }}</td>
                                <td class="text-xs font-semibold text-slate-500">{{ $recording->reviewed_at?->format('Y-m-d') ?? '—' }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ $recording->audioUrl() }}" target="_blank" rel="noopener"
                                           class="rounded-lg p-2 text-slate-400 transition hover:bg-mihrab-50 hover:text-mihrab-700"
                                           aria-label="تشغيل التسجيل">
                                            <x-icon name="play" class="h-4 w-4" />
                                        </a>
                                        <a href="{{ $recording->audioUrl() }}" download
                                           class="rounded-lg p-2 text-slate-400 transition hover:bg-mihrab-50 hover:text-mihrab-700"
                                           aria-label="تنزيل التسجيل">
                                            <x-icon name="download" class="h-4 w-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div>{{ $this->recordings->links() }}</div>
    @endif

    <section class="card border border-rose-200 bg-rose-50/40 p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h2 class="flex items-center gap-2 text-base font-black text-rose-900">
                    <x-icon name="alert" class="h-4 w-4" />
                    تصفير المنصة وإعادة التسميع من جديد
                </h2>
                <p class="mt-1 text-xs font-semibold leading-6 text-rose-800/80">
                    صدّر مجموعة البيانات أولًا. بعد التصفير سيتم حذف جميع التسجيلات وملفاتها الصوتية نهائيًا،
                    وإعادة كل مهام الآيات إلى حالة «متاحة» ليبدأ الطلاب دورة تسميع جديدة.
                </p>
                <p class="mt-2 inline-flex rounded-lg bg-white/70 px-2.5 py-1 text-[11px] font-black text-rose-700">
                    لا يمكن التراجع عن هذه العملية.
                </p>
            </div>

            <button type="button" wire:click="openResetModal" class="btn btn-danger btn-sm">
                <x-icon name="trash" class="h-4 w-4" />
                تصفير المنصة
            </button>
        </div>
    </section>

    <x-modal :show="$showResetModal" title="تصفير المنصة وإعادة التسميع" close="cancelReset" max-width="max-w-md">
        <div class="space-y-4">
            <p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold leading-6 text-rose-800">
                سيتم حذف <span class="font-black tabular">{{ number_format($this->totalRecordings) }}</span> تسجيلًا
                (بما فيها التسجيلات المعلّقة والمرفوضة) مع ملفاتها الصوتية، وإعادة كل المهام إلى «متاحة».
                تأكد من تنزيل حزمة البيانات قبل المتابعة.
            </p>

            @if (count($query) > 0)
                <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold leading-6 text-amber-900">
                    تنبيه: التصدير الحالي مقيّد بالفلاتر المطبّقة، لكن التصفير يحذف جميع التسجيلات بلا استثناء.
                </p>
            @endif

            <div>
                <label for="reset-confirmation" class="label">اكتب كلمة «تصفير» للتأكيد</label>
                <input id="reset-confirmation" type="text" wire:model="resetConfirmation"
                       class="input" autocomplete="off" placeholder="تصفير">
                @error('resetConfirmation') <p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-slot:footer>
            <button type="button" wire:click="cancelReset" class="btn btn-secondary btn-sm">إلغاء</button>
            <button type="button" wire:click="resetPlatform" wire:loading.attr="disabled"
                    class="btn btn-danger btn-sm">
                <span wire:loading.remove wire:target="resetPlatform">تأكيد التصفير</span>
                <span wire:loading wire:target="resetPlatform">جارٍ التصفير…</span>
            </button>
        </x-slot:footer>
    </x-modal>
</div>
