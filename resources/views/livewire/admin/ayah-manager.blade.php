<div class="space-y-5">
    <div class="card card-pad">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-1 flex-wrap items-end gap-3">
                <div class="min-w-52 flex-1 sm:max-w-sm">
                    <label for="ayah-search" class="label">بحث في النص</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                            <x-icon name="search" class="h-4 w-4" />
                        </span>
                        <input id="ayah-search" type="search" wire:model.live.debounce.400ms="search"
                               class="input pr-9" placeholder="كلمة من نص الآية…">
                    </div>
                </div>

                <div class="min-w-44">
                    <label for="ayah-surah" class="label">السورة</label>
                    <select id="ayah-surah" wire:model.live="surahFilter" class="select">
                        <option value="all">كل السور</option>
                        @foreach ($surahs as $number => $name)
                            <option value="{{ $number }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <span class="label">بلاغات الطلاب</span>
                    <button type="button" wire:click="toggleReported"
                            class="chip {{ $onlyReported ? 'chip-active' : '' }}">
                        <x-icon name="alert" class="h-3.5 w-3.5" />
                        المبلَّغ عنها ({{ number_format($this->stats['reports']) }})
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="importSamples" wire:confirm="سيتم إضافة الآيات الجاهزة غير الموجودة مسبقًا. هل تريد المتابعة؟"
                        wire:loading.attr="disabled" class="btn btn-secondary btn-sm">
                    <x-icon name="database" class="h-4 w-4" />
                    استيراد العينات
                </button>
                <button type="button" wire:click="openCreate" class="btn btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    آية جديدة
                </button>
            </div>
        </div>

        <div class="mt-4 grid gap-2 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-sand-50 px-3 py-2">
                <p class="text-[10px] font-bold text-slate-500">إجمالي الآيات</p>
                <p class="text-lg font-black tabular text-slate-900">{{ number_format($this->stats['ayahs']) }}</p>
            </div>
            <div class="rounded-xl bg-sand-50 px-3 py-2">
                <p class="text-[10px] font-bold text-slate-500">اكتمال المصحف</p>
                <p class="text-lg font-black tabular text-slate-900">{{ $this->stats['coverage'] }}%</p>
            </div>
            <div class="rounded-xl bg-sand-50 px-3 py-2">
                <p class="text-[10px] font-bold text-slate-500">السور المشمولة</p>
                <p class="text-lg font-black tabular text-slate-900">{{ number_format($this->stats['surahs']) }} / 114</p>
            </div>
            <div class="rounded-xl bg-sand-50 px-3 py-2">
                <p class="text-[10px] font-bold text-slate-500">آيات لها تسجيلات</p>
                <p class="text-lg font-black tabular text-slate-900">{{ number_format($this->stats['recorded']) }}</p>
            </div>
        </div>
    </div>

    <section class="card card-pad">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 text-base font-black text-slate-900">
                    <x-icon name="database" class="h-4 w-4 text-mihrab-700" />
                    استيراد المصحف الكامل (Excel / CSV)
                </h2>
                <p class="mt-1 max-w-2xl text-xs font-semibold leading-6 text-slate-500">
                    ارفع ملف <span class="font-black tabular">Quran_Full_Data.xlsx</span> أو نسخة CSV بنفس الأعمدة
                    (المعرّف، اسم السورة، رقم السورة، رقم الآية، النص). يقارن الاستيراد النص المخزَّن
                    بالملف حرفًا بحرف — بما في ذلك كل الحركات والتجويد — ويستبدل النص عند اختلافه،
                    ثم يعرض لك أرقام الآيات التي تم استبدالها.
                </p>
            </div>
            <span class="badge badge-gold tabular">
                {{ number_format($this->stats['ayahs']) }} / {{ number_format($this->stats['expected']) }}
            </span>
        </div>

        <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
            <div>
                <label for="dataset-file" class="label">ملف البيانات</label>
                <input id="dataset-file" type="file" wire:model="dataset" accept=".xlsx,.csv,.txt"
                       class="block w-full cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-mihrab-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-mihrab-800 hover:file:bg-mihrab-100">
                @error('dataset') <p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                <p class="mt-1.5 text-[11px] font-semibold text-slate-400">
                    الحد الأقصى 20 ميجابايت. يُشتق معرّف الآية تلقائيًا بصيغة ستة أرقام (مثال: 001003).
                </p>
            </div>

            <button type="button" wire:click="importDataset" wire:loading.attr="disabled" wire:target="importDataset,dataset"
                    class="btn btn-primary btn-sm">
                <span wire:loading.remove wire:target="importDataset">بدء الاستيراد</span>
                <span wire:loading wire:target="importDataset">جارٍ الاستيراد…</span>
            </button>
        </div>

        @if ($lastImport !== null)
            <div class="mt-4 rounded-xl border border-slate-100 bg-sand-50 px-4 py-3">
                <p class="text-xs font-black text-slate-800">آخر استيراد: {{ $lastImport['source'] }}</p>
                <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-bold">
                    <span class="badge badge-success">جديد {{ number_format($lastImport['inserted']) }}</span>
                    <span class="badge badge-warning">محدَّث {{ number_format($lastImport['updated']) }}</span>
                    <span class="badge badge-neutral">دون تغيير {{ number_format($lastImport['unchanged']) }}</span>
                    <span class="badge badge-neutral">متجاهل {{ number_format($lastImport['skipped']) }}</span>
                    <span class="badge {{ $lastImport['invalid'] > 0 ? 'badge-danger' : 'badge-neutral' }}">غير صالح {{ number_format($lastImport['invalid']) }}</span>
                </div>

                @if ($lastImport['errors'] !== [])
                    <ul class="mt-2 space-y-1 text-[11px] font-semibold leading-5 text-rose-700">
                        @foreach ($lastImport['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($lastImport['replaced'] !== [])
                    <div class="mt-3 border-t border-amber-200/70 pt-2">
                        <p class="text-[11px] font-black text-amber-900">
                            تم استبدال نص {{ number_format(count($lastImport['replaced'])) }} آية:
                        </p>
                        <div class="mt-1.5 flex max-h-28 flex-wrap gap-1 overflow-y-auto">
                            @foreach (array_slice($lastImport['replaced'], 0, 200) as $code)
                                <span class="badge badge-warning tabular">{{ $code }}</span>
                            @endforeach
                            @if (count($lastImport['replaced']) > 200)
                                <span class="text-[11px] font-bold text-amber-900">
                                    و{{ number_format(count($lastImport['replaced']) - 200) }} أخرى…
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </section>

    @if ($this->ayahs->isEmpty())
        <x-empty-state icon="book" title="لا توجد آيات"
                       description="ابدأ باستيراد العينات الجاهزة أو أضف آية جديدة يدويًا بالرسم العثماني.">
            <x-slot:actions>
                <button type="button" wire:click="importSamples" class="btn btn-primary btn-sm">استيراد العينات</button>
                <button type="button" wire:click="openCreate" class="btn btn-secondary btn-sm">إضافة آية</button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="card overflow-hidden">
            <ul class="divide-y divide-slate-100">
                @foreach ($this->ayahs as $ayah)
                    <li wire:key="ayah-{{ $ayah->id }}" class="px-4 py-3 sm:px-5">
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold">
                                    <span class="badge badge-neutral tabular">{{ $ayah->code }}</span>
                                    <span class="text-mihrab-800">{{ $ayah->reference }}</span>
                                    <span class="badge badge-neutral tabular">{{ $ayah->recordings_count }} تسجيل</span>
                                    @if ($ayah->pending_reports_count > 0)
                                        <span class="badge badge-danger">{{ $ayah->pending_reports_count }} بلاغ</span>
                                    @endif
                                </div>
                                <p class="ayah-text mt-1.5 line-clamp-2 text-right text-lg leading-loose text-slate-800">{{ $ayah->text_uthmani }}</p>
                            </div>

                            <div class="flex items-center gap-1">
                                @if ($ayah->pending_reports_count > 0)
                                    <button type="button" wire:click="resolveReports({{ $ayah->id }})"
                                            class="rounded-lg p-2 text-mihrab-600 transition hover:bg-mihrab-50 hover:text-mihrab-800"
                                            title="تمت معالجة البلاغات"
                                            aria-label="معالجة بلاغات {{ $ayah->reference }}">
                                        <x-icon name="check-circle" class="h-4 w-4" />
                                    </button>
                                @endif

                                <button type="button" wire:click="openEdit({{ $ayah->id }})"
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-mihrab-50 hover:text-mihrab-700"
                                        aria-label="تعديل {{ $ayah->reference }}">
                                    <x-icon name="edit" class="h-4 w-4" />
                                </button>

                                <button type="button" wire:click="delete({{ $ayah->id }})"
                                        wire:confirm="هل تريد حذف هذه الآية نهائيًا؟"
                                        @disabled($ayah->recordings_count > 0)
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40"
                                        aria-label="حذف {{ $ayah->reference }}">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>{{ $this->ayahs->links() }}</div>
    @endif

    <x-modal :show="$showModal" :title="$editingId ? 'تعديل آية' : 'إضافة آية'" close="closeModal">
    <form wire:submit="save" id="ayah-form" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="ayah-surah-input" class="label">السورة</label>
                <select id="ayah-surah-input" wire:model.live="surah_number" class="select">
                    @foreach ($surahs as $number => $name)
                        <option value="{{ $number }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('surah_number') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="ayah-number-input" class="label">رقم الآية</label>
                <input id="ayah-number-input" type="number" min="1" max="286" wire:model="ayah_number"
                       class="input tabular" required>
                @error('ayah_number') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="ayah-text" class="label">نص الآية بالرسم العثماني</label>
            <textarea id="ayah-text" wire:model="text_uthmani" rows="4" maxlength="2000"
                      class="textarea ayah-text text-xl leading-loose"
                      placeholder="بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ" required></textarea>
            @error('text_uthmani') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
            <p class="mt-1.5 text-[11px] font-semibold text-slate-400">
                {{ mb_strlen($text_uthmani) }} / 2000 حرف ·
                المعرّف المحسوب:
                <span class="tabular font-black text-mihrab-700">{{ \App\Support\Quran::formatAyahId(\App\Support\Quran::ayahId((int) $surah_number, (int) $ayah_number)) }}</span>
            </p>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" wire:click="closeModal" class="btn btn-secondary btn-sm">إلغاء</button>
        <button type="submit" form="ayah-form" wire:loading.attr="disabled" class="btn btn-primary btn-sm">
            <span wire:loading.remove wire:target="save">{{ $editingId ? 'حفظ التعديلات' : 'إضافة الآية' }}</span>
            <span wire:loading wire:target="save">جارٍ الحفظ…</span>
        </button>
    </x-slot:footer>
    </x-modal>
</div>
