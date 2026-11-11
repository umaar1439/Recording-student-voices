<x-layouts.guest title="تسجيل الدخول" heading="أهلًا بعودتك" subheading="سجّل دخولك لمتابعة تسجيل التلاوات ومراجعتها.">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="label">البريد الإلكتروني</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   dir="ltr" class="input text-left" placeholder="you@example.com">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="label">كلمة المرور</label>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   dir="ltr" class="input text-left" placeholder="••••••••">
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex cursor-pointer items-center gap-2.5 text-sm font-semibold text-slate-600">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                   class="h-4 w-4 rounded border-slate-300 text-mihrab-700 focus:ring-mihrab-500">
            تذكّرني على هذا الجهاز
        </label>

        <button type="submit" class="btn btn-primary w-full">
            <x-icon name="arrow-left" class="h-4.5 w-4.5" />
            تسجيل الدخول
        </button>

        <p class="text-center text-xs leading-6 text-slate-400">
            الدخول مخصّص للحسابات المفعّلة. إن واجهت مشكلة تواصل مع مدير المنصّة.
        </p>
    </form>

    <x-slot:footer>
        ليس لديك حساب؟
        <a href="{{ route('register') }}" class="font-black text-mihrab-700 hover:text-mihrab-900">أنشئ حساب طالب</a>
    </x-slot:footer>
</x-layouts.guest>
