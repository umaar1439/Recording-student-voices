<x-layouts.guest title="إنشاء حساب" heading="أنشئ حساب طالب" subheading="ابدأ رحلتك في تسجيل التلاوات وانتظر مراجعة المُعلّم.">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="label">الاسم الكامل</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name"
                   maxlength="100" class="input" placeholder="مثال: عبد الله محمد">
            @error('name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="label">البريد الإلكتروني</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username"
                   dir="ltr" class="input text-left" placeholder="you@example.com">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        @if ($teachers->isNotEmpty())
            <div>
                <label for="teacher_id" class="label">المعلم المشرف</label>
                <select id="teacher_id" name="teacher_id" required class="select">
                    <option value="" disabled @selected(old('teacher_id') === null || old('teacher_id') === '')>
                        اختر معلمك…
                    </option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected((string) old('teacher_id') === (string) $teacher->id)>
                            {{ $teacher->name }}
                        </option>
                    @endforeach
                </select>
                @error('teacher_id')
                    <p class="error">{{ $message }}</p>
                @enderror
                <p class="hint">ستظهر تسجيلاتك لمعلمك المختار فقط للمراجعة.</p>
            </div>
        @else
            <div class="rounded-2xl border border-gold-200 bg-gold-50/70 px-4 py-3 text-xs font-semibold leading-6 text-mihrab-900">
                لا يوجد معلمون مسجّلون حاليًا، لذا ستتم مراجعة تسجيلاتك من قِبل الإدارة.
            </div>
        @endif

        <div>
            <label for="password" class="label">كلمة المرور</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   dir="ltr" class="input text-left" placeholder="8 أحرف على الأقل">
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
            <p class="hint">استخدم 8 أحرف أو أكثر، ويُفضّل مزج الأحرف والأرقام.</p>
        </div>

        <div>
            <label for="password_confirmation" class="label">تأكيد كلمة المرور</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   dir="ltr" class="input text-left" placeholder="أعد كتابة كلمة المرور">
        </div>

        <div class="rounded-2xl border border-mihrab-100 bg-mihrab-50/70 px-4 py-3 text-xs font-semibold leading-6 text-mihrab-900">
            يتم إنشاء حساب «طالب» تلقائيًا، ويُراجع المعلم المختار تسجيلاتك. حسابات المُعلّمين والإداريين تُنشأ من لوحة التحكم فقط.
        </div>

        <button type="submit" class="btn btn-primary w-full">
            <x-icon name="check" class="h-4.5 w-4.5" />
            إنشاء الحساب
        </button>
    </form>

    <x-slot:footer>
        لديك حساب بالفعل؟
        <a href="{{ route('login') }}" class="font-black text-mihrab-700 hover:text-mihrab-900">تسجيل الدخول</a>
    </x-slot:footer>
</x-layouts.guest>
