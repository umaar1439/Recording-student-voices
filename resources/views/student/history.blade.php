<x-layouts.app title="سجلّ تلاواتي" heading="سجلّ تلاواتي" subheading="تابع حالة كل تسجيل، واستمع لما أرسلته، واقرأ ملاحظات المُعلّم.">
    <x-slot:actions>
        <a href="{{ route('student.recite') }}" class="btn btn-primary btn-sm">
            <x-icon name="mic" class="h-4 w-4" />
            تسجيل جديد
        </a>
    </x-slot:actions>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="إجمالي التسجيلات" :value="$summary['total']" icon="database" tone="slate" />
        <x-stat-card label="بانتظار المراجعة" :value="$summary['pending']" icon="clock" tone="amber" />
        <x-stat-card label="مقبولة" :value="$summary['approved']" icon="check-circle" tone="mihrab" />
        <x-stat-card label="مرفوضة" :value="$summary['rejected']" icon="x-circle" tone="rose" />
    </div>

    <div class="mt-6">
        @if ($recordings->isEmpty())
            <x-empty-state icon="mic" title="لم تسجّل أي آية بعد"
                           description="ابدأ من محراب التسجيل، اختر سورة، وسجّل الآية الأولى لتظهر هنا مع حالتها.">
                <x-slot:actions>
                    <a href="{{ route('student.recite') }}" class="btn btn-primary btn-sm">ابدأ التسجيل الآن</a>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="space-y-3 lg:hidden">
                @foreach ($recordings as $recording)
                    <article class="card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-900">{{ $recording->ayah->reference }}</p>
                                <p class="mt-0.5 flex items-center gap-2 text-[11px] font-semibold text-slate-400">
                                    <span class="tabular">{{ $recording->taskCode() }}</span>
                                    <span>·</span>
                                    <span>{{ $recording->created_at->diffForHumans() }}</span>
                                </p>
                            </div>
                            @include('student.partials.status-badge', ['recording' => $recording])
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="badge {{ $recording->type === 'correct' ? 'badge-success' : 'badge-danger' }}">{{ $recording->typeLabel() }}</span>
                            @if ($recording->duration_ms)
                                <span class="text-[11px] font-bold tabular text-slate-400">{{ gmdate('i:s', (int) round($recording->duration_ms / 1000)) }} دقيقة</span>
                            @endif
                        </div>

                        <div class="mt-3">
                            <x-audio-player :src="route('recordings.audio', $recording)" />
                        </div>

                        @if ($recording->review_notes)
                            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-6 text-amber-900">
                                <span class="font-black">ملاحظة المُعلّم: </span>{{ $recording->review_notes }}
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="card hidden overflow-hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الآية</th>
                                <th>النوع</th>
                                <th>الحالة</th>
                                <th>المدة</th>
                                <th>أُرسل</th>
                                <th>الملاحظات</th>
                                <th class="w-64">الاستماع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recordings as $recording)
                                <tr>
                                    <td class="font-bold text-slate-900">
                                        {{ $recording->ayah->reference }}
                                        <span class="mt-0.5 block text-[10px] font-semibold tabular text-slate-400">{{ $recording->taskCode() }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $recording->type === 'correct' ? 'badge-success' : 'badge-danger' }}">{{ $recording->typeLabel() }}</span>
                                    </td>
                                    <td>@include('student.partials.status-badge', ['recording' => $recording])</td>
                                    <td class="tabular text-xs font-semibold text-slate-500">
                                        {{ $recording->duration_ms ? gmdate('i:s', (int) round($recording->duration_ms / 1000)) : '—' }}
                                    </td>
                                    <td class="text-xs font-semibold text-slate-500">{{ $recording->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="max-w-56 text-xs leading-6 text-slate-500">{{ $recording->review_notes ?: '—' }}</td>
                                    <td>
                                        <x-audio-player :src="route('recordings.audio', $recording)" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $recordings->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
