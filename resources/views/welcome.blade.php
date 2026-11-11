<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#022c24">
    <meta name="description" content="مِحراب التلاوة — منصّة لتسجيل التلاوات القرآنية ومراجعتها وبناء مجموعة بيانات صوتية موثوقة.">

    <title>مِحراب التلاوة · منصّة التلاوات القرآنية</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-sand-50 font-sans text-slate-800 antialiased">
    <header class="sticky top-0 z-40 border-b border-sand-200/70 bg-sand-50/85 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
            <x-brand />

            <nav class="hidden items-center gap-1 md:flex" aria-label="أقسام الصفحة">
                <a href="#features" class="nav-link">المزايا</a>
                <a href="#workflow" class="nav-link">كيف تعمل؟</a>
                <a href="#roles" class="nav-link">الأدوار</a>
            </nav>

            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">
                        <x-icon name="arrow-left" class="h-4 w-4" />
                        مساحة عملي
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-secondary btn-sm">تسجيل الدخول</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">
                        ابدأ التسجيل
                        <x-icon name="arrow-left" class="h-4 w-4" />
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        <section class="relative overflow-hidden bg-mihrab-950 text-white">
            <div class="pointer-events-none absolute inset-0 opacity-50 pattern-grid"></div>
            <div class="pointer-events-none absolute -right-32 -top-24 h-96 w-96 rounded-full bg-mihrab-600/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -left-24 bottom-0 h-80 w-80 rounded-full bg-gold-500/10 blur-3xl"></div>

            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8 lg:py-24">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1.5 text-[11px] font-bold text-gold-400">
                        <x-icon name="sparkles" class="h-3.5 w-3.5" />
                        مجموعة بيانات صوتية قرآنية موثّقة
                    </span>

                    <h1 class="mt-6 text-4xl font-black leading-[1.2] tracking-tight sm:text-5xl lg:text-[3.4rem]">
                        سجّل تلاوتك،<br>
                        <span class="text-gold-400">وراجعها آية بآية.</span>
                    </h1>

                    <p class="mt-5 max-w-xl text-base leading-8 text-white/65 sm:text-lg">
                        منصّة متكاملة للطلاب والمُعلّمين والإدارة: تسجيل منظّم للآيات، مراجعة سريعة باعتماد أو رفض مع ملاحظة، ثم تصدير مجموعة بيانات جاهزة للتدريب.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-gold btn-lg">
                                <x-icon name="mic" class="h-5 w-5" />
                                انتقل إلى مساحة عملي
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="btn btn-gold btn-lg">
                                <x-icon name="mic" class="h-5 w-5" />
                                أنشئ حساب الطالب
                            </a>
                            <a href="{{ route('login') }}" class="btn btn-lg border border-white/20 bg-white/5 text-white hover:bg-white/10">
                                لديك حساب؟ سجّل الدخول
                            </a>
                        @endauth
                    </div>

                    <dl class="mt-10 grid max-w-lg grid-cols-3 gap-4">
                        @foreach ([
                            ['value' => number_format($stats['ayahs']), 'label' => 'آية متاحة'],
                            ['value' => number_format($stats['recordings']), 'label' => 'تسجيل معتمد'],
                            ['value' => number_format($stats['students']), 'label' => 'طالب مسجّل'],
                        ] as $stat)
                            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                <dt class="text-2xl font-black tabular text-gold-400">{{ $stat['value'] }}</dt>
                                <dd class="mt-0.5 text-[11px] font-semibold text-white/55">{{ $stat['label'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="relative mx-auto w-full max-w-md">
                    <div class="absolute inset-x-6 -top-3 h-full rounded-[2.5rem] bg-gold-500/10"></div>

                    <div class="relative overflow-hidden rounded-[2.5rem] border border-white/15 bg-gradient-to-b from-white/10 to-white/[0.03] p-7 shadow-2xl backdrop-blur">
                        <div class="flex items-center justify-between">
                            <span class="badge badge-gold">مصحف التجربة</span>
                            <span class="text-[11px] font-bold text-white/40">سورة الفاتحة</span>
                        </div>

                        <p class="ayah-text mt-8 text-2xl text-white sm:text-[1.7rem]">
                            بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ
                        </p>

                        <div class="mt-8 flex items-center justify-center">
                            <span class="relative flex h-20 w-20 items-center justify-center rounded-full bg-rose-500/90 text-white shadow-2xl shadow-rose-950/40 recorder-ring">
                                <x-icon name="mic" class="h-8 w-8" />
                            </span>
                        </div>

                        <div class="mt-4 flex items-center justify-center gap-2 text-xs font-bold text-white/50">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-rose-400"></span>
                            جارٍ التسجيل · 00:12
                        </div>

                        <div class="mt-6 flex items-end justify-center gap-1" aria-hidden="true">
                            @foreach ([10, 18, 26, 14, 30, 22, 12, 34, 20, 16, 28, 10] as $bar)
                                <span class="w-1.5 rounded-full bg-gold-400/70 animate-equalizer" style="height: {{ $bar }}px; animation-delay: {{ $loop->index * 60 }}ms"></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="mx-auto max-w-2xl text-center">
                <span class="badge badge-success">المزايا</span>
                <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">كل ما تحتاجه لبناء مجموعة تلاوات موثوقة</h2>
                <p class="mt-3 text-sm leading-7 text-slate-500 sm:text-base">
                    من أول ضغطة تسجيل إلى ملف ZIP جاهز للتدريب، كل خطوة مصمّمة لتكون واضحة وسريعة على الهاتف والحاسب.
                </p>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['icon' => 'mic', 'title' => 'تسجيل مباشر من المتصفح', 'body' => 'لا حاجة لتطبيق: سجّل من الميكروفون مباشرة، مع مؤقّت، معاينة، وإعادة تسجيل قبل الإرسال.'],
                    ['icon' => 'book', 'title' => 'عرض الآيات بخط قرآني', 'body' => 'واجهة محراب تعرض الآية بخط «أميري قرآن» مع تمييز السورة ورقم الآية والجزء.'],
                    ['icon' => 'headphones', 'title' => 'مراجعة بملاحظات', 'body' => 'يعتمد المُعلّم التسجيل أو يرفضه مع ملاحظة مكتوبة، مع فلاتر حسب النوع والسورة والطالب.'],
                    ['icon' => 'shield', 'title' => 'أدوار وصلاحيات', 'body' => 'ثلاثة أدوار واضحة: طالب، مُعلّم، مدير — مع حماية كل مسار بحسب الصلاحية.'],
                    ['icon' => 'download', 'title' => 'تصدير متعدّد الصيغ', 'body' => 'نزّل التلاوات المعتمدة بصيغة ZIP مع ملف JSON للبيانات الوصفية، أو CSV للجداول.'],
                    ['icon' => 'chart', 'title' => 'إحصاءات فورية', 'body' => 'لوحة تحكم تعرض أعداد الآيات والتسجيلات ونسب الاعتماد وتوزيع الأنواع لحظة بلحظة.'],
                ] as $feature)
                    <article class="card group p-6 transition hover:-translate-y-1 hover:shadow-lg hover:shadow-mihrab-900/10">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-mihrab-50 text-mihrab-700 transition group-hover:bg-mihrab-700 group-hover:text-white">
                            <x-icon :name="$feature['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 text-base font-black text-slate-900">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm leading-7 text-slate-500">{{ $feature['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="workflow" class="border-y border-sand-200 bg-sand-100/60">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                <div class="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                    <div>
                        <span class="badge badge-gold">كيف تعمل؟</span>
                        <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">أربع خطوات من التلاوة إلى البيانات</h2>
                        <p class="mt-3 text-sm leading-7 text-slate-500 sm:text-base">
                            دورة عمل بسيطة تحفظ جودة التسجيلات وتجعل المراجعة سريعة ومنظّمة.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ route('register') }}" class="btn btn-primary">
                                <x-icon name="mic" class="h-4 w-4" />
                                ابدأ الآن مجانًا
                            </a>
                            <a href="{{ route('login') }}" class="btn btn-secondary">تسجيل الدخول</a>
                        </div>
                    </div>

                    <ol class="space-y-3">
                        @foreach ([
                            ['title' => 'اختر السورة والآية', 'body' => 'تصفّح المصحف داخل المنصّة وانتقل للآية المطلوبة من لوحة التسجيل.'],
                            ['title' => 'سجّل تلاوتك', 'body' => 'اضغط زر التسجيل، ورتّل الآية، ثم راجع التسجيل قبل إرساله للمراجعة.'],
                            ['title' => 'مراجعة المُعلّم', 'body' => 'يستمع المُعلّم للتسجيل ويعتمده أو يرفضه مع ملاحظة تُظهر للطالب فورًا.'],
                            ['title' => 'التصدير للتدريب', 'body' => 'تُجمع التلاوات المعتمدة في مجموعة بيانات قابلة للتنزيل بصيغ متعددة.'],
                        ] as $step)
                            <li class="flex items-start gap-4 rounded-2xl border border-sand-200 bg-white p-4 shadow-sm">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-mihrab-700 text-sm font-black text-white tabular">{{ $loop->iteration }}</span>
                                <div>
                                    <p class="text-sm font-black text-slate-900">{{ $step['title'] }}</p>
                                    <p class="mt-1 text-xs leading-6 text-slate-500">{{ $step['body'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>

        <section id="roles" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="mx-auto max-w-2xl text-center">
                <span class="badge badge-success">الأدوار</span>
                <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">ثلاث مساحات عمل متكاملة</h2>
            </div>

            <div class="mt-12 grid gap-5 lg:grid-cols-3">
                @foreach ([
                    ['icon' => 'user', 'tone' => 'bg-mihrab-50 text-mihrab-700', 'title' => 'الطالب', 'body' => 'يتصفّح المصحف، يسجّل الآيات المطلوبة، ويتابع حالة كل تسجيل وملاحظات المُعلّم.', 'items' => ['تسجيل آية بآية', 'إعادة التسجيل قبل الإرسال', 'متابعة حالة الاعتماد والملاحظات']],
                    ['icon' => 'headphones', 'tone' => 'bg-gold-400/15 text-gold-600', 'title' => 'المُعلّم', 'body' => 'يستمع للتسجيلات المعلّقة، يعتمدها أو يرفضها مع ملاحظة، ويبحث حسب السورة أو النوع.', 'items' => ['قائمة مراجعة بفلاتر', 'مشغّل صوت بسرعات متعددة', 'ملاحظة إلزامية عند الرفض']],
                    ['icon' => 'shield', 'tone' => 'bg-slate-900 text-gold-400', 'title' => 'المدير', 'body' => 'يدير المستخدمين والآيات ويتابع إحصاءات المنصّة ويصدّر مجموعة البيانات المعتمدة.', 'items' => ['إدارة الحسابات والأدوار', 'إدارة الآيات والسور', 'تصدير ZIP / JSON / CSV']],
                ] as $role)
                    <article class="card flex flex-col p-6">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $role['tone'] }}">
                            <x-icon :name="$role['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 text-lg font-black text-slate-900">{{ $role['title'] }}</h3>
                        <p class="mt-2 text-sm leading-7 text-slate-500">{{ $role['body'] }}</p>

                        <ul class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                            @foreach ($role['items'] as $item)
                                <li class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <x-icon name="check" class="h-4 w-4 shrink-0 text-mihrab-600" />
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8 lg:pb-24">
            <div class="relative overflow-hidden rounded-[2rem] bg-mihrab-950 px-6 py-12 text-center text-white sm:px-12 sm:py-16">
                <div class="pointer-events-none absolute inset-0 opacity-40 pattern-grid"></div>
                <div class="pointer-events-none absolute left-1/2 top-0 h-64 w-64 -translate-x-1/2 rounded-full bg-gold-500/10 blur-3xl"></div>

                <div class="relative mx-auto max-w-2xl">
                    <p class="font-quran text-2xl leading-[2.2] text-gold-400 sm:text-3xl">﴿ وَرَتِّلِ الْقُرْآنَ تَرْتِيلًا ﴾</p>
                    <h2 class="mt-4 text-2xl font-black sm:text-3xl">ابدأ بتسجيل أول آية اليوم</h2>
                    <p class="mt-3 text-sm leading-7 text-white/60 sm:text-base">
                        أنشئ حسابك كطالب، وسجّل تلاوتك الأولى في أقل من دقيقة. المُعلّم سيراجعها ويترك لك ملاحظاته.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('register') }}" class="btn btn-gold btn-lg">
                            <x-icon name="mic" class="h-5 w-5" />
                            إنشاء حساب
                        </a>
                        <a href="{{ route('login') }}" class="btn btn-lg border border-white/20 bg-white/5 text-white hover:bg-white/10">
                            تسجيل الدخول
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-sand-200 bg-sand-50">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 sm:flex-row sm:px-6 lg:px-8">
            <x-brand />
            <p class="text-xs font-semibold text-slate-400">© {{ now()->year }} مِحراب التلاوة — جميع الحقوق محفوظة.</p>
        </div>
    </footer>
    </main>
</body>
</html>
