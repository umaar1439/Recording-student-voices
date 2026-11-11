@props(['title' => null, 'heading' => null, 'subheading' => null])

<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#022c24">
    <meta name="description" content="منصّة تسجيل ومراجعة وإدارة مجموعة بيانات التلاوات القرآنية.">

    <title>{{ $title ?? 'مِحراب التلاوة' }} · مِحراب التلاوة</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:wght@400;700&family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-sand-50 font-sans text-slate-800 antialiased">
    <div class="grid min-h-dvh lg:grid-cols-2">
        {{-- Brand / showcase panel --}}
        <div class="relative hidden overflow-hidden bg-mihrab-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute inset-0 opacity-60 pattern-grid"></div>
            <div class="pointer-events-none absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-gold-500/10 blur-3xl"></div>

            <div class="relative">
                <x-brand variant="dark" />
            </div>

            <div class="relative space-y-8">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-8 backdrop-blur">
                    <p class="font-quran text-3xl leading-[2.2] text-gold-400">﴿ وَرَتِّلِ الْقُرْآنَ تَرْتِيلًا ﴾</p>
                    <p class="mt-4 text-sm font-semibold text-white/60">سورة المزّمّل — الآية ٤</p>
                </div>

                <div class="space-y-4">
                    @foreach ([
                        ['icon' => 'mic', 'title' => 'تسجيل منظّم للآيات', 'body' => 'واجهة تسجيل مبنية على المصحف، آية بآية، مع مؤقّت ومعاينة فورية.'],
                        ['icon' => 'headphones', 'title' => 'مراجعة من المُعلّم', 'body' => 'اعتماد أو رفض مع ملاحظة واضحة، ليصل التسجيل جاهزًا لمجموعة البيانات.'],
                        ['icon' => 'database', 'title' => 'تصدير جاهز للتدريب', 'body' => 'تنزيل التلاوات المعتمدة مع بياناتها الوصفية بصيغة ZIP أو JSON أو CSV.'],
                    ] as $feature)
                        <div class="flex items-start gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-gold-400">
                                <x-icon :name="$feature['icon']" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-sm font-bold text-white">{{ $feature['title'] }}</p>
                                <p class="mt-1 text-xs leading-6 text-white/55">{{ $feature['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="relative text-xs font-semibold text-white/35">© {{ now()->year }} مِحراب التلاوة — جميع الحقوق محفوظة.</p>
        </div>

        {{-- Form panel --}}
        <div class="flex flex-col justify-center px-5 py-10 sm:px-10 lg:px-16">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <x-brand />
                </div>

                <div class="mb-8">
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ $heading }}</h1>
                    @if ($subheading)
                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ $subheading }}</p>
                    @endif
                </div>

                @if (session('status'))
                    <div class="mb-6 rounded-2xl border border-mihrab-200 bg-mihrab-50 px-4 py-3 text-sm font-semibold text-mihrab-900">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="card p-6 sm:p-7">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="mt-6 text-center text-sm font-semibold text-slate-500">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </div>

    <div x-data="toastStack" class="pointer-events-none fixed inset-x-0 top-4 z-[80] flex flex-col items-center gap-2 px-4">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-xl animate-toast-in">
                <x-icon name="info" class="mt-0.5 h-5 w-5 shrink-0" />
                <p class="flex-1 text-sm font-semibold leading-6" x-text="toast.message"></p>
                <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100" @click="dismiss(toast.id)" aria-label="إغلاق">
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
