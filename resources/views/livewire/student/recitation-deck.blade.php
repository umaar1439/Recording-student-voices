@php
    $task = $this->tasks->first();
@endphp

<div class="space-y-5">
    <div class="card card-pad">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-1 flex-wrap items-end gap-3">
                <div class="min-w-48 flex-1 sm:max-w-xs">
                    <label for="surah-filter" class="label">السورة</label>
                    <select id="surah-filter" wire:model.live="surahFilter" class="select">
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
                            <button type="button" wire:click="setTypeFilter('{{ $value }}')"
                                    class="chip {{ $typeFilter === $value ? 'chip-active' : '' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if (count($skipped) > 0)
                <button type="button" wire:click="resetSkipped" class="btn btn-secondary btn-sm">
                    <x-icon name="refresh" class="h-4 w-4" />
                    إرجاع المتخطّاة ({{ count($skipped) }})
                </button>
            @endif
        </div>

        @if ($this->teachers->isNotEmpty())
            <div class="mt-5 grid gap-3 rounded-2xl border border-mihrab-100 bg-mihrab-50/50 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                <div class="sm:max-w-xs">
                    <label for="teacher-select" class="label">المعلم المشرف</label>
                    <select id="teacher-select" wire:model="teacherId" class="select">
                        <option value="">اختر معلمك…</option>
                        @foreach ($this->teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected($teacherId === $teacher->id)>{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                    @error('teacherId')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>
                <p class="text-[11px] font-semibold leading-6 text-slate-500 sm:max-w-xs">
                    يُرسَل كل تسجيل إلى معلمك المختار للمراجعة، ويمكنك تغييره من هنا قبل الإرسال.
                </p>
            </div>
        @else
            <p class="mt-5 rounded-2xl border border-gold-200 bg-gold-50/70 px-4 py-3 text-[11px] font-semibold leading-6 text-mihrab-900">
                لا يوجد معلمون مسجّلون حاليًا، لذا تتم مراجعة تسجيلاتك من قِبل الإدارة.
            </p>
        @endif

        <div class="mt-5 border-t border-slate-100 pt-4">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-bold">
                <span class="text-slate-500">
                    التقدّم: <span class="tabular text-slate-900">{{ $this->progress['done'] }}</span>
                    من <span class="tabular text-slate-900">{{ $this->progress['total'] }}</span> مهمة
                </span>
                <span class="tabular text-mihrab-700">{{ $this->progress['percent'] }}%</span>
            </div>
            <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-sand-200">
                <div class="h-full rounded-full bg-gradient-to-l from-mihrab-600 to-mihrab-400 transition-all duration-500"
                     style="width: {{ $this->progress['percent'] }}%"></div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <span class="badge badge-success">{{ $stats['approved'] }} معتمد</span>
                <span class="badge badge-warning">{{ $stats['pending'] }} قيد المراجعة</span>
                <span class="badge badge-neutral">{{ $stats['today'] }} اليوم</span>
                <span class="badge badge-gold">{{ $this->progress['remaining'] }} متبقّية</span>
            </div>
        </div>
    </div>

    @error('audio')
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
            {{ $message }}
        </div>
    @enderror

    @error('teacherId')
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
            {{ $message }}
        </div>
    @enderror

    @if ($task === null)
        <x-empty-state icon="check-circle" title="أتممت كل المهام المتاحة"
                       description="لا توجد آيات متبقّية في الفلتر الحالي. جرّب سورة أخرى أو أعد ضبط الفلاتر، وإذا اكتملت جميع مهام المصحف فقد انتهت دورة التسميع حتى تصفير المنصة من الإدارة.">
            <x-slot:actions>
                <button type="button" wire:click="setTypeFilter('all')" class="btn btn-primary btn-sm">إعادة ضبط النوع</button>
                <a href="{{ route('student.history') }}" class="btn btn-secondary btn-sm">عرض سجلّ تلاواتي</a>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
            <article wire:key="task-{{ $task['key'] }}" class="card overflow-hidden">
                <div class="relative overflow-hidden px-5 py-5 text-white sm:px-7 {{ $task['type'] === 'wrong' ? 'bg-rose-700' : 'bg-mihrab-950' }}">
                    @if ($task['type'] !== 'wrong')
                        <div class="pointer-events-none absolute inset-0 opacity-50 pattern-grid"></div>
                    @endif

                    <div class="relative flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold tracking-wide text-gold-400">{{ $task['ayah']->surah_name }}</p>
                            <h2 class="mt-0.5 text-lg font-black">الآية {{ $task['ayah']->ayah_number }}</h2>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <span class="badge badge-neutral tabular" title="معرّف المهمة">{{ $task['code'] }}</span>
                            <span class="badge {{ $task['type'] === 'correct' ? 'badge-success' : 'badge-danger' }}">
                                {{ \App\Models\Recording::typeLabels()[$task['type']] }}
                            </span>
                            @if ($task['reserved_until'] !== null)
                                <span class="badge badge-gold tabular"
                                      title="هذه الآية محجوزة لك وحدك حتى انتهاء المهلة">
                                    محجوزة لك حتى {{ $task['reserved_until']->format('H:i') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="ayah-surface px-5 py-9 sm:px-8 sm:py-11">
                    <p class="ayah-text text-2xl text-slate-900 sm:text-[1.9rem]">
                        {{ $task['task']->text_uthmani }}
                    </p>

                    <div class="mt-6 flex items-center justify-center gap-2 text-[11px] font-semibold text-slate-400">
                        <span class="h-px w-8 bg-slate-300"></span>
                        <span class="tabular">{{ $task['ayah']->surah_number }}:{{ $task['ayah']->ayah_number }}</span>
                        <span class="h-px w-8 bg-slate-300"></span>
                    </div>
                </div>

                <div class="border-t border-sand-200 bg-sand-50/70 px-5 py-6 sm:px-7"
                     wire:key="recorder-{{ $task['key'] }}"
                     x-data="recitationRecorder({ maxSeconds: {{ $task['max_seconds'] }}, uploadUrl: '{{ route('student.recordings.upload') }}' })">
                    <div x-show="!supported" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold leading-6 text-amber-900">
                        متصفحك لا يدعم تسجيل الصوت. استخدم إصدارًا حديثًا من Chrome أو Firefox أو Edge، وتأكد من فتح الموقع عبر HTTPS أو localhost.
                    </div>

                    <div x-show="supported" x-cloak class="flex flex-col items-center gap-4">
                        </div>

                        <div x-show="state === 'idle'" class="flex flex-col items-center gap-3">
                            <button type="button" @click="toggle()"
                                    class="flex h-20 w-20 items-center justify-center rounded-full bg-mihrab-700 text-white shadow-xl shadow-mihrab-900/25 transition hover:scale-105 hover:bg-mihrab-800"
                                    aria-label="بدء التسجيل">
                                <x-icon name="mic" class="h-8 w-8" />
                            </button>
                            <p class="text-center text-xs font-semibold text-slate-500">
                                اضغط الميكروفون لبدء التسجيل — الحد الأقصى
                                <span class="tabular" x-text="formattedLimit"></span>
                                حسب طول الآية، ويُحوَّل الصوت إلى WAV 16kHz.
                            </p>
                        </div>

                        <div x-show="state === 'requesting'" x-cloak class="flex flex-col items-center gap-3 py-4">
                            <x-icon name="spinner" class="h-8 w-8 animate-spin text-mihrab-700" />
                            <p class="text-xs font-semibold text-slate-500">جارٍ طلب إذن الميكروفون…</p>
                        </div>

                        <div x-show="state === 'recording'" x-cloak class="flex w-full flex-col items-center gap-4">
                            <div class="flex items-center gap-2 text-sm font-black text-rose-600">
                                <span class="h-2 w-2 animate-pulse rounded-full bg-rose-600"></span>
                                جارٍ التسجيل
                                <span class="tabular" x-text="formattedTime"></span>
                            </div>

                            <div class="flex h-10 items-end gap-1" aria-hidden="true">
                                @foreach ([12, 22, 32, 18, 38, 26, 16, 30, 20, 14, 34, 24] as $bar)
                                    <span class="w-1.5 rounded-full bg-rose-400 animate-equalizer" style="height: {{ $bar }}px; animation-delay: {{ $loop->index * 70 }}ms"></span>
                                @endforeach
                            </div>

                            <button type="button" @click="stop()"
                                    class="relative flex h-20 w-20 items-center justify-center rounded-full bg-rose-600 text-white shadow-xl shadow-rose-900/30 recorder-ring"
                                    aria-label="إيقاف التسجيل">
                                <span class="h-6 w-6 rounded-md bg-white"></span>
                            </button>

                            <div class="w-full max-w-sm">
                                <div class="h-1.5 overflow-hidden rounded-full bg-rose-100">
                                    <div class="h-full rounded-full bg-rose-500 transition-all duration-300" :style="`width: ${elapsedPercent}%`"></div>
                                </div>
                                <p class="mt-1.5 text-center text-[10px] font-bold tabular text-slate-400" x-text="`${elapsedPercent}% من الوقت المتاح`"></p>
                            </div>
                        </div>

                        <div x-show="state === 'processing'" x-cloak class="flex flex-col items-center gap-3 py-4">
                            <x-icon name="spinner" class="h-8 w-8 animate-spin text-mihrab-700" />
                            <p class="text-xs font-semibold text-slate-500">جارٍ معالجة التسجيل وتحويله إلى WAV 16kHz أحادي القناة…</p>
                        </div>

                        <div x-show="state === 'recorded' || state === 'uploading'" x-cloak class="w-full space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="badge badge-success">
                                    <x-icon name="check-circle" class="h-3.5 w-3.5" />
                                    تسجيل WAV جاهز (16kHz)
                                </span>
                                <span class="text-[11px] font-bold tabular text-slate-500" x-text="`المدة: ${formattedDuration}`"></span>
                            </div>

                            <audio :src="previewUrl" controls preload="metadata" class="w-full rounded-xl"></audio>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="upload('{{ $task['key'] }}')"
                                        :disabled="isBusy" wire:loading.attr="disabled"
                                        class="btn btn-primary flex-1">
                                    <x-icon name="upload" class="h-4 w-4" />
                                    <span x-show="state !== 'uploading'">إرسال للمراجعة</span>
                                    <span x-show="state === 'uploading'" x-cloak class="tabular" x-text="`جارٍ الرفع ${Math.round(progress)}%`"></span>
                                </button>

                                <button type="button" @click="discard()" :disabled="isBusy" class="btn btn-secondary">
                                    <x-icon name="refresh" class="h-4 w-4" />
                                    إعادة التسجيل
                                </button>
                            </div>

                            <div x-show="state === 'uploading'" x-cloak>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full rounded-full bg-mihrab-600 transition-all duration-200" :style="`width: ${progress}%`"></div>
                                </div>
                            </div>
                        </div>

                        <p x-show="error" x-cloak x-text="error" class="w-full rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold leading-6 text-rose-700"></p>

                        <div class="flex w-full flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="skip('{{ $task['key'] }}')" class="btn btn-ghost btn-sm">
                                <x-icon name="arrow-left" class="h-4 w-4" />
                                تخطّي هذه الآية مؤقتًا
                            </button>

                            <span class="text-[11px] font-semibold text-slate-400">
                                يُحفظ التسجيل بعد الرفع ويظهر في سجلّك بحالة «بانتظار المراجعة».
                            </span>
                        </div>
                    </div>

                    {{-- نافذة الأذونات: تُنقل إلى <body> حتى لا يقصّها overflow البطاقة. --}}
                    <template x-teleport="body">
                        <div x-show="permissionModal" x-cloak
                             x-transition.opacity.duration.200ms
                             class="fixed inset-0 z-[90] flex items-end justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm sm:items-center sm:p-6"
                             role="dialog" aria-modal="true" aria-labelledby="permission-modal-title"
                             @keydown.escape.window="closePermissionModal()">
                            <div x-show="permissionModal"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                                 x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
                                 x-transition:leave-end="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                                 @click.outside="closePermissionModal()"
                                 class="relative w-full max-w-lg overflow-hidden rounded-3xl border border-sand-200 bg-white shadow-2xl shadow-slate-950/30">

                                <div class="relative overflow-hidden bg-mihrab-900 px-5 py-5 text-white">
                                    <div class="pattern-grid pointer-events-none absolute inset-0 opacity-40"></div>

                                    <div class="relative flex items-start gap-3">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gold-400/15 text-gold-400 ring-1 ring-gold-400/30">
                                            <x-icon name="mic" class="h-5 w-5" />
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <p class="text-[11px] font-bold tracking-wide text-gold-400">إذن الميكروفون</p>
                                            <h2 id="permission-modal-title" class="mt-0.5 text-base font-black leading-6"
                                                x-text="permissionTitle"></h2>
                                            <p class="mt-1 text-[11px] font-semibold text-white/65" x-text="deviceLabel"></p>
                                        </div>

                                        <button type="button" @click="closePermissionModal()"
                                                class="rounded-xl p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                                                aria-label="إغلاق النافذة">
                                            <x-icon name="x" class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                                <div class="max-h-[58vh] space-y-4 overflow-y-auto px-5 py-5">
                                    <p class="text-xs font-semibold leading-6 text-slate-600" x-text="permissionIntro"></p>

                                    <div x-show="embedded || inApp"
                                         class="flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-[11px] font-semibold leading-6 text-amber-900">
                                        <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                                        <p x-text="containerMessage"></p>
                                    </div>

                                    {{-- كروم أندرويد يحظر الطلب بصمت بعد أول رفض، فلا تظهر رسالة الإذن أبدًا. --}}
                                    <div x-show="androidChrome && !inApp && !embedded" x-cloak
                                         class="rounded-2xl border border-gold-400/50 bg-sand-50 p-3.5">
                                        <div class="flex items-start gap-2.5">
                                            <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gold-400/15 text-gold-600 ring-1 ring-gold-400/40">
                                                <span class="absolute inset-0 animate-ping rounded-xl bg-gold-400/25"></span>
                                                <x-icon name="shield" class="relative h-4 w-4" />
                                            </span>

                                            <div class="min-w-0">
                                                <p class="text-[11px] font-black text-slate-900">إصلاح إذن الميكروفون في كروم أندرويد</p>
                                                <p class="mt-0.5 text-[11px] font-semibold leading-5 text-slate-600">
                                                    بعد أول رفض يتجاهل كروم طلب الإذن بصمت ولا تظهر أي رسالة. انسخ رابط الموقع، ثم افتح إعدادات كروم وألصقه في بحث «إعدادات المواقع».
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-3 space-y-2">
                                            <button type="button" @click="openChromeSettings()" class="btn btn-primary btn-sm w-full">
                                                <x-icon name="shield" class="h-4 w-4" />
                                                <span x-text="settingsCopied ? 'تم نسخ رابط الموقع ✓' : 'نسخ رابط الموقع لفتح إعدادات كروم'"></span>
                                            </button>

                                            <div x-show="settingsCopied" x-transition.opacity.duration.150ms
                                                 class="flex items-start gap-2 rounded-xl border border-mihrab-200 bg-mihrab-50 px-3 py-2.5 text-[11px] font-semibold leading-5 text-mihrab-900">
                                                <x-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                                                <span>كروم (⋮) ← «الإعدادات» ← «إعدادات المواقع» ← «الميكروفون» ← الصق الرابط في البحث ← «السماح»، ثم اضغط «إعادة المحاولة».</span>
                                            </div>

                                            <label class="block">
                                                <span class="text-[10px] font-bold text-slate-500">أو انسخ الرابط يدويًا</span>
                                                <input type="text" readonly :value="siteLink" @focus="$el.select()" dir="ltr"
                                                       class="mt-1 w-full rounded-xl border border-sand-300 bg-white px-3 py-2 text-[11px] font-semibold text-slate-600" />
                                            </label>
                                        </div>
                                    </div>

                                    <ol class="space-y-2.5">
                                        <template x-for="(step, index) in permissionSteps" :key="index">
                                            <li class="flex items-start gap-3">
                                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-mihrab-50 text-[10px] font-black text-mihrab-800 ring-1 ring-mihrab-200 tabular"
                                                      x-text="index + 1"></span>
                                                <span class="text-[11px] font-semibold leading-6 text-slate-700" x-text="step"></span>
                                            </li>
                                        </template>
                                    </ol>

                                    <p class="flex items-start gap-2 rounded-2xl bg-sand-50 px-3.5 py-3 text-[11px] font-semibold leading-6 text-slate-500">
                                        <x-icon name="sparkles" class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" />
                                        <span>بعد السماح بالميكروفون ستُغلق هذه النافذة ويبدأ التسجيل تلقائيًا دون تحديث الصفحة.</span>
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4">
                                    <button type="button" id="permission-modal-retry" @click="retryPermission()"
                                            :disabled="isBusy" class="btn btn-primary btn-sm flex-1">
                                        <x-icon name="refresh" class="h-4 w-4" />
                                        إعادة المحاولة
                                    </button>

                                    <button type="button" x-show="embedded || inApp" @click="openExternalBrowser()"
                                            class="btn btn-secondary btn-sm">
                                        <x-icon name="arrow-right" class="h-4 w-4" />
                                        فتح في متصفح خارجي
                                    </button>

                                    <button type="button" @click="forceResetMicrophone()" class="btn btn-ghost btn-sm">
                                        <x-icon name="refresh" class="h-4 w-4" />
                                        <span x-text="androidChrome ? 'إعادة ضبط الميكروفون وتحديث الصفحة' : 'تحديث الصفحة'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </article>

            <aside class="space-y-5">
                <section class="card card-pad">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base font-black text-slate-900">التالي في القائمة</h2>
                        <span class="badge badge-neutral">{{ $this->progress['remaining'] }} متبقّية</span>
                    </div>

                    <ul class="mt-4 space-y-2">
                        @forelse ($this->upcoming as $next)
                            <li wire:key="next-{{ $next['key'] }}" class="rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-black text-slate-800">
                                        {{ $next['ayah']->surah_name }} · {{ $next['ayah']->ayah_number }}
                                        <span class="tabular text-[10px] font-bold text-slate-400">{{ $next['code'] }}</span>
                                    </p>
                                    <span class="badge {{ $next['type'] === 'correct' ? 'badge-success' : 'badge-danger' }}">
                                        {{ \App\Models\Recording::typeLabels()[$next['type']] }}
                                    </span>
                                </div>
                            </li>
                        @empty
                            <li class="text-xs font-semibold text-slate-400">لا مهام إضافية في القائمة.</li>
                        @endforelse
                    </ul>

                    <a href="{{ route('student.history') }}" class="btn btn-secondary btn-sm mt-4 w-full">
                        <x-icon name="history" class="h-4 w-4" />
                        سجلّ تلاواتي
                    </a>
                </section>

                <section class="card card-pad bg-mihrab-50/40">
                    <h2 class="flex items-center gap-2 text-sm font-black text-mihrab-900">
                        <x-icon name="info" class="h-4 w-4" />
                        إرشادات التسجيل
                    </h2>
                    <ul class="mt-3 space-y-2 text-[11px] font-semibold leading-6 text-slate-600">
                        <li class="flex gap-2"><span class="text-gold-600">◆</span> اقرأ الآية كاملةً بترتيل واضح دون موسيقى خلفية.</li>
                        <li class="flex gap-2"><span class="text-gold-600">◆</span> تأكد من هدوء المكان واقترابك من الميكروفون.</li>
                        <li class="flex gap-2"><span class="text-gold-600">◆</span> استمع لتسجيلك قبل الإرسال، وأعد التسجيل إن لزم.</li>
                        <li class="flex gap-2"><span class="text-gold-600">◆</span> يُحوَّل التسجيل تلقائيًا إلى WAV أحادي 16kHz قبل الرفع.</li>
                        <li class="flex gap-2"><span class="text-gold-600">◆</span> يمكنك تخطّي الآية مؤقتًا والعودة إليها لاحقًا.</li>
                    </ul>
                </section>
            </aside>
        </div>
    @endif
</div>
