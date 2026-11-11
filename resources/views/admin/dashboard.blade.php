<x-layouts.app title="لوحة التحكم" heading="لوحة التحكم" subheading="نظرة شاملة على المستخدمين والآيات والتسجيلات وحالة مجموعة البيانات.">
    <x-slot:actions>
        @if ($counts['reports'] > 0)
            <a href="{{ route('admin.ayahs.index', ['reported' => 1]) }}" class="btn btn-danger btn-sm">
                <x-icon name="alert" class="h-4 w-4" />
                بلاغات الآيات ({{ number_format($counts['reports']) }})
            </a>
        @endif
        <a href="{{ route('teacher.queue') }}" class="btn btn-secondary btn-sm">
            <x-icon name="headphones" class="h-4 w-4" />
            مراجعة المعلّق ({{ number_format($counts['pending']) }})
        </a>
        <a href="{{ route('admin.datasets.index') }}" class="btn btn-primary btn-sm">
            <x-icon name="download" class="h-4 w-4" />
            تصدير البيانات
        </a>
    </x-slot:actions>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="إجمالي التسجيلات" :value="number_format($counts['recordings'])" icon="database" tone="slate"
                     :hint="$counts['pending'].' بانتظار المراجعة'" />
        <x-stat-card label="التسجيلات المعتمدة" :value="number_format($counts['approved'])" icon="check-circle" tone="mihrab"
                     :hint="'نسبة الاعتماد '.$stats['approval_rate'].'%'" />
        <x-stat-card label="المستخدمون" :value="number_format($counts['users'])" icon="users" tone="gold"
                     :hint="$counts['students'].' طالب · '.$counts['teachers'].' معلّم'" />
        <x-stat-card label="الآيات المتاحة" :value="number_format($counts['ayahs'])" icon="book" tone="amber"
                     :hint="'موزّعة على '.$surahCoverage->count().' سورة'" />
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-[1.25fr_0.75fr]">
        <section class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-black text-slate-900">حالة المراجعة</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-400">توزيع التسجيلات بحسب الحالة والنوع</p>
                </div>
                <span class="badge badge-success">{{ $stats['approval_rate'] }}% معدّل الاعتماد</span>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['label' => 'بانتظار المراجعة', 'value' => $counts['pending'], 'color' => 'bg-amber-500', 'track' => 'bg-amber-100'],
                    ['label' => 'معتمدة', 'value' => $counts['approved'], 'color' => 'bg-mihrab-600', 'track' => 'bg-mihrab-100'],
                    ['label' => 'مرفوضة', 'value' => $counts['rejected'], 'color' => 'bg-rose-500', 'track' => 'bg-rose-100'],
                ] as $row)
                    @php $percent = $counts['recordings'] > 0 ? (int) round($row['value'] / $counts['recordings'] * 100) : 0; @endphp
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-3.5">
                        <p class="text-xs font-bold text-slate-500">{{ $row['label'] }}</p>
                        <p class="mt-1 text-xl font-black tabular text-slate-900">{{ number_format($row['value']) }}</p>
                        <div class="mt-2 h-2 overflow-hidden rounded-full {{ $row['track'] }}">
                            <div class="h-full rounded-full {{ $row['color'] }}" style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="mt-1 text-[10px] font-bold tabular text-slate-400">{{ $percent }}%</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
                <div>
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span class="flex items-center gap-2 text-slate-600">
                            <span class="h-2 w-2 rounded-full bg-mihrab-600"></span>
                            تلاوة صحيحة
                        </span>
                        <span class="tabular text-slate-900">{{ number_format($counts['correct']) }} · {{ $stats['correct_share'] }}%</span>
                    </div>
                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-mihrab-100">
                        <div class="h-full rounded-full bg-mihrab-600" style="width: {{ $stats['correct_share'] }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span class="flex items-center gap-2 text-slate-600">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                            تلاوة خاطئة
                        </span>
                        <span class="tabular text-slate-900">{{ number_format($counts['wrong']) }} · {{ $stats['wrong_share'] }}%</span>
                    </div>
                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-rose-100">
                        <div class="h-full rounded-full bg-rose-500" style="width: {{ $stats['wrong_share'] }}%"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card card-pad">
            <h2 class="text-base font-black text-slate-900">تغطية السور</h2>
            <p class="mt-0.5 text-xs font-semibold text-slate-400">السور الأكثر احتواءً للآيات</p>

            <ul class="mt-4 space-y-2.5">
                @forelse ($surahCoverage as $surah)
                    @php $max = $surahCoverage->max('total') ?: 1; @endphp
                    <li>
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span class="text-slate-700">{{ $surah->surah_name ?: 'سورة '.\App\Support\Quran::surahName((int) $surah->surah_number) }}</span>
                            <span class="tabular text-slate-400">{{ number_format($surah->total) }}</span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-sand-200">
                            <div class="h-full rounded-full bg-gold-500" style="width: {{ (int) round($surah->total / $max * 100) }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="text-xs font-semibold text-slate-400">لم تُضف آيات بعد.</li>
                @endforelse
            </ul>

            <a href="{{ route('admin.ayahs.index') }}" class="btn btn-secondary btn-sm mt-5 w-full">
                <x-icon name="book" class="h-4 w-4" />
                إدارة الآيات
            </a>
        </section>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-[1.35fr_0.65fr]">
        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-base font-black text-slate-900">أحدث التسجيلات</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-400">آخر ما وصل إلى المنصّة</p>
                </div>
                <a href="{{ route('teacher.queue') }}" class="text-xs font-black text-mihrab-700 hover:text-mihrab-900">فتح المراجعة ←</a>
            </div>

            @if ($latestRecordings->isEmpty())
                <div class="px-5 py-10 text-center text-sm font-semibold text-slate-400">لا توجد تسجيلات حتى الآن.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الطالب</th>
                                <th>الآية</th>
                                <th>النوع</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($latestRecordings as $recording)
                                <tr>
                                    <td class="font-bold text-slate-900">{{ $recording->user?->name ?? '—' }}</td>
                                    <td class="text-xs font-semibold text-slate-600">
                                        {{ $recording->ayah->reference }}
                                        <span class="mt-0.5 block text-[10px] font-semibold tabular text-slate-400">{{ $recording->taskCode() }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $recording->type === 'correct' ? 'badge-success' : 'badge-danger' }}">{{ $recording->typeLabel() }}</span>
                                    </td>
                                    <td>@include('student.partials.status-badge', ['recording' => $recording])</td>
                                    <td class="text-xs font-semibold text-slate-500">{{ $recording->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card card-pad">
            <h2 class="text-base font-black text-slate-900">أكثر الطلاب اعتمادًا</h2>
            <p class="mt-0.5 text-xs font-semibold text-slate-400">حسب عدد التسجيلات المعتمدة</p>

            <ol class="mt-4 space-y-3">
                @forelse ($topStudents as $student)
                    <li class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-mihrab-50 text-xs font-black text-mihrab-800">
                            {{ $student->initials() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-black text-slate-800">{{ $student->name }}</p>
                            <p class="text-[10px] font-semibold text-slate-400">طالب</p>
                        </div>
                        <span class="badge {{ $student->approved_count > 0 ? 'badge-success' : 'badge-neutral' }}">
                            {{ number_format($student->approved_count) }}
                        </span>
                    </li>
                @empty
                    <li class="text-xs font-semibold text-slate-400">لا يوجد طلاب مسجّلون بعد.</li>
                @endforelse
            </ol>

            <div class="mt-5 grid gap-2">
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">
                    <x-icon name="users" class="h-4 w-4" />
                    إدارة المستخدمين
                </a>
                <a href="{{ route('admin.ayahs.index') }}" class="btn btn-secondary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    إضافة آية جديدة
                </a>
            </div>
        </section>
    </div>
</x-layouts.app>
