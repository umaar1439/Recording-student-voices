@props(['title' => null, 'heading' => null, 'subheading' => null])

@php
    $user = auth()->user();

    $items = [];

    if ($user?->isStudent()) {
        $items[] = ['label' => 'محراب التسجيل', 'route' => 'student.recite', 'icon' => 'mic', 'active' => request()->routeIs('student.recite')];
        $items[] = ['label' => 'تصفّح الآيات', 'route' => 'student.read', 'icon' => 'book', 'active' => request()->routeIs('student.read')];
        $items[] = ['label' => 'سجلّ تلاواتي', 'route' => 'student.history', 'icon' => 'clock', 'active' => request()->routeIs('student.history')];
    }

    if ($user?->canReview()) {
        $items[] = ['label' => 'قائمة المراجعة', 'route' => 'teacher.queue', 'icon' => 'headphones', 'active' => request()->routeIs('teacher.*')];
    }

    if ($user?->isAdmin()) {
        $items[] = ['label' => 'لوحة التحكم', 'route' => 'admin.dashboard', 'icon' => 'chart', 'active' => request()->routeIs('admin.dashboard')];
        $items[] = ['label' => 'المستخدمون', 'route' => 'admin.users.index', 'icon' => 'users', 'active' => request()->routeIs('admin.users.*')];
        $items[] = ['label' => 'آيات القرآن', 'route' => 'admin.ayahs.index', 'icon' => 'book', 'active' => request()->routeIs('admin.ayahs.*')];
        $items[] = ['label' => 'مجموعة البيانات', 'route' => 'admin.datasets.index', 'icon' => 'database', 'active' => request()->routeIs('admin.datasets.*')];
    }

    $pendingQuery = \App\Models\Recording::query();

    if ($user?->isTeacher()) {
        $pendingQuery->whereHas('user', fn ($student) => $student->where('teacher_id', $user->id));
    }

    $pending = $user?->canReview() ? $pendingQuery->pending()->count() : 0;
    $pageTitle = $title ?? $heading ?? 'مِحراب التلاوة';
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#022c24">
    <meta name="description" content="منصّة تسجيل ومراجعة وإدارة مجموعة بيانات التلاوات القرآنية.">

    <title>{{ $pageTitle }} · مِحراب التلاوة</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:wght@400;700&family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-sand-50 font-sans text-slate-800 antialiased">
    <div x-data="{ drawer: false }" class="min-h-dvh lg:grid lg:grid-cols-[286px_minmax(0,1fr)]">
        <aside class="relative hidden border-l border-mihrab-900/40 bg-mihrab-950 text-white lg:flex lg:min-h-dvh lg:flex-col">
            <div class="pointer-events-none absolute inset-0 opacity-60 pattern-grid"></div>

            <div class="relative flex items-center gap-3 border-b border-white/10 px-5 py-6">
                <x-brand variant="dark" />
            </div>

            <div class="relative flex-1 space-y-6 overflow-y-auto px-4 py-6">
                <div>
                    <p class="mb-2 px-3 text-[11px] font-bold tracking-wider text-white/40">مساحة العمل</p>
                    <x-sidebar-nav :items="$items" :pending="$pending" />
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center gap-2 text-gold-400">
                        <x-icon name="sparkles" class="h-4 w-4" />
                        <p class="text-xs font-bold">إرشاد سريع</p>
                    </div>
                    <p class="mt-2 text-xs leading-6 text-white/60">
                        @if ($user?->isStudent())
                            سجّل كل آية بصوت واضح، ثم انتظر مراجعة المُعلّم لتُضاف إلى مجموعة البيانات.
                        @elseif ($user?->isTeacher())
                            راجع التسجيلات بسرعة، واكتب ملاحظة عند الرفض لمساعدة الطالب على التحسّن.
                        @else
                            يمكنك إدارة المستخدمين والآيات وتصدير مجموعة البيانات المعتمدة بصيغة ZIP جاهزة.
                        @endif
                    </p>
                </div>
            </div>

            <div class="relative border-t border-white/10 p-4">
                <div class="flex items-center gap-3 rounded-2xl bg-white/5 p-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gold-500 text-sm font-black text-mihrab-950">
                        {{ $user?->initials() }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-white">{{ $user?->name }}</p>
                        <p class="truncate text-[11px] font-semibold text-white/50">{{ $user?->roleLabel() }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl p-2 text-white/60 transition hover:bg-white/10 hover:text-white" title="تسجيل الخروج" aria-label="تسجيل الخروج">
                            <x-icon name="logout" class="h-5 w-5" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        <div x-cloak x-show="drawer" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
            <div x-show="drawer" x-transition.opacity class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="drawer = false"></div>

            <div x-show="drawer"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 @keydown.escape.window="drawer = false"
                 class="absolute inset-y-0 right-0 flex w-[86%] max-w-xs flex-col bg-mihrab-950 text-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-white/10 px-4 py-5">
                    <x-brand variant="dark" />
                    <button type="button" class="rounded-xl p-2 text-white/70 hover:bg-white/10" @click="drawer = false" aria-label="إغلاق القائمة">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-4">
                    <x-sidebar-nav :items="$items" :pending="$pending" />
                </div>

                <div class="border-t border-white/10 p-4">
                    <div class="flex items-center gap-3 rounded-2xl bg-white/5 p-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gold-500 text-sm font-black text-mihrab-950">{{ $user?->initials() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold">{{ $user?->name }}</p>
                            <p class="text-[11px] font-semibold text-white/50">{{ $user?->roleLabel() }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-xl p-2 text-white/60 hover:bg-white/10 hover:text-white" aria-label="تسجيل الخروج">
                                <x-icon name="logout" class="h-5 w-5" />
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex min-h-dvh flex-col">
            <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-sand-50/85 backdrop-blur lg:hidden">
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <button type="button" class="rounded-2xl border border-slate-200 bg-white p-2.5 text-slate-600 shadow-sm transition hover:text-mihrab-800" @click="drawer = true" aria-label="فتح القائمة">
                        <x-icon name="menu" class="h-5 w-5" />
                    </button>

                    <x-brand />

                    <a href="{{ route('dashboard') }}" class="flex h-10 w-10 items-center justify-center rounded-2xl bg-mihrab-800 text-xs font-black text-gold-400 shadow-sm" aria-label="لوحة التحكم">
                        {{ $user?->initials() }}
                    </a>
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl flex-1 px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-12 lg:pt-10">
                @if ($heading)
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ $heading }}</h1>
                            @if ($subheading)
                                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-500">{{ $subheading }}</p>
                            @endif
                        </div>

                        @isset($actions)
                            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                        @endisset
                    </div>
                @endif

                @if (session('status'))
                    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-mihrab-200 bg-mihrab-50 px-4 py-3 text-sm font-semibold text-mihrab-900">
                        <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-mihrab-700" />
                        <p class="flex-1 leading-6">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any() && ! isset($showErrors))
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </main>

            <footer class="hidden border-t border-slate-200/70 px-8 py-5 text-xs font-semibold text-slate-400 lg:block">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
                    <p>© {{ now()->year }} مِحراب التلاوة — جميع الحقوق محفوظة.</p>
                    <p class="font-quran text-sm text-slate-500">﴿ وَرَتِّلِ الْقُرْآنَ تَرْتِيلًا ﴾</p>
                </div>
            </footer>

            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur safe-bottom lg:hidden" aria-label="التنقل السريع">
                <div class="mx-auto grid max-w-lg grid-cols-4 gap-1 px-2 py-2">
                    @foreach (array_slice($items, 0, 4) as $item)
                        <a href="{{ route($item['route']) }}"
                           @class([
                               'flex flex-col items-center gap-1 rounded-xl px-1 py-1.5 text-[10px] font-bold transition',
                               'bg-mihrab-50 text-mihrab-900' => $item['active'],
                               'text-slate-500 hover:text-mihrab-800' => ! $item['active'],
                           ])>
                            <x-icon :name="$item['icon']" class="h-5 w-5" />
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        </div>
    </div>

    <div x-data="toastStack" class="pointer-events-none fixed inset-x-0 top-4 z-[80] flex flex-col items-center gap-2 px-4">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-2xl border px-4 py-3 shadow-xl animate-toast-in"
                 :class="{
                     'border-mihrab-200 bg-white text-mihrab-900': toast.type === 'success',
                     'border-rose-200 bg-white text-rose-800': toast.type === 'error',
                     'border-slate-200 bg-white text-slate-700': toast.type !== 'success' && toast.type !== 'error',
                 }">
                <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" x-show="toast.type === 'success'" />
                <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" x-show="toast.type === 'error'" />
                <x-icon name="info" class="mt-0.5 h-5 w-5 shrink-0" x-show="toast.type !== 'success' && toast.type !== 'error'" />
                <p class="flex-1 text-sm font-semibold leading-6" x-text="toast.message"></p>
                <button type="button" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" @click="dismiss(toast.id)" aria-label="إغلاق التنبيه">
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
