@php
    $roleTabs = [
        'all' => ['label' => 'الكل', 'count' => $this->counts['all']],
        'student' => ['label' => $roleLabels['student'], 'count' => $this->counts['student']],
        'teacher' => ['label' => $roleLabels['teacher'], 'count' => $this->counts['teacher']],
        'admin' => ['label' => $roleLabels['admin'], 'count' => $this->counts['admin']],
    ];
@endphp

<div class="space-y-5">
    <div class="card card-pad">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-1 flex-wrap items-end gap-3">
                <div class="min-w-52 flex-1 sm:max-w-sm">
                    <label for="user-search" class="label">بحث</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                            <x-icon name="search" class="h-4 w-4" />
                        </span>
                        <input id="user-search" type="search" wire:model.live.debounce.400ms="search"
                               class="input pr-9" placeholder="الاسم أو البريد الإلكتروني…">
                    </div>
                </div>

                <div>
                    <span class="label">الدور</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($roleTabs as $value => $tab)
                            <button type="button" wire:click="$set('roleFilter', '{{ $value }}')"
                                    class="chip {{ $roleFilter === $value ? 'chip-active' : '' }}">
                                {{ $tab['label'] }}
                                <span class="tabular {{ $roleFilter === $value ? 'text-white/80' : 'text-slate-400' }}">({{ $tab['count'] }})</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <button type="button" wire:click="openCreate" class="btn btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                مستخدم جديد
            </button>
        </div>
    </div>

    @if ($this->users->isEmpty())
        <x-empty-state icon="users" title="لا يوجد مستخدمون مطابقون"
                       description="لم نجد أي مستخدم يطابق البحث أو الدور المحدد. جرّب تعديل معايير البحث.">
            <x-slot:actions>
                <button type="button" wire:click="$set('search', '')" class="btn btn-secondary btn-sm">مسح البحث</button>
                <button type="button" wire:click="openCreate" class="btn btn-primary btn-sm">إضافة مستخدم</button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="card overflow-hidden">
            <div class="hidden grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)_minmax(0,0.8fr)_minmax(0,0.9fr)] gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-2.5 text-[11px] font-black uppercase tracking-wide text-slate-400 lg:grid">
                <span>المستخدم</span>
                <span>الدور</span>
                <span>التسجيلات</span>
                <span class="text-left">إجراءات</span>
            </div>

            <ul class="divide-y divide-slate-100">
                @foreach ($this->users as $user)
                    <li wire:key="user-{{ $user->id }}" class="px-4 py-3 sm:px-5">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-mihrab-50 text-xs font-black text-mihrab-800">
                                    {{ $user->initials() }}
                                </span>
                                <div class="min-w-0">
                                    <p class="flex items-center gap-1.5 truncate text-sm font-black text-slate-900">
                                        {{ $user->name }}
                                        @if ($user->id === auth()->id())
                                            <span class="badge badge-gold">أنت</span>
                                        @endif
                                    </p>
                                    <p class="truncate text-[11px] font-semibold text-slate-400">{{ $user->email }}</p>
                                    @if ($user->isStudent())
                                        <p class="mt-0.5 truncate text-[11px] font-semibold {{ $user->teacher ? 'text-mihrab-700' : 'text-amber-600' }}">
                                            {{ $user->teacher ? 'المعلم: '.$user->teacher->name : 'بدون معلم مشرف' }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:flex-1">
                                <label class="sr-only" for="role-{{ $user->id }}">دور {{ $user->name }}</label>
                                <select id="role-{{ $user->id }}"
                                        wire:change="updateRole({{ $user->id }}, $event.target.value)"
                                        @disabled($user->id === auth()->id())
                                        class="select select-sm w-auto min-w-32">
                                    @foreach ($roleLabels as $value => $label)
                                        <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>

                                <span class="badge badge-neutral tabular">{{ number_format($user->recordings_count) }} تسجيل</span>
                            </div>

                            <div class="ms-auto flex items-center gap-1 lg:ms-0">
                                <button type="button" wire:click="openEdit({{ $user->id }})"
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-mihrab-50 hover:text-mihrab-700"
                                        aria-label="تعديل {{ $user->name }}">
                                    <x-icon name="edit" class="h-4 w-4" />
                                </button>

                                <button type="button" wire:click="deleteUser({{ $user->id }})"
                                        wire:confirm="سيتم حذف المستخدم وكل تسجيلاته نهائيًا. هل تريد المتابعة؟"
                                        @disabled($user->id === auth()->id())
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:opacity-40"
                                        aria-label="حذف {{ $user->name }}">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>{{ $this->users->links() }}</div>
    @endif

    <x-modal :show="$showModal" :title="$editingId ? 'تعديل مستخدم' : 'إضافة مستخدم'" close="closeModal">
    <form wire:submit="save" class="space-y-4" id="user-form">
        <div>
            <label for="user-name" class="label">الاسم الكامل</label>
            <input id="user-name" type="text" wire:model="name" class="input" autocomplete="name" required>
            @error('name') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="user-email" class="label">البريد الإلكتروني</label>
            <input id="user-email" type="email" dir="ltr" wire:model="email" class="input text-left" autocomplete="email" required>
            @error('email') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>

        @if ($role === \App\Models\User::ROLE_STUDENT)
            <div>
                <label for="user-teacher" class="label">المعلم المشرف</label>
                @if ($this->teachers->isNotEmpty())
                    <select id="user-teacher" wire:model="teacherId" class="select" required>
                        <option value="" disabled @selected($teacherId === null)>اختر معلمًا…</option>
                        @foreach ($this->teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected($teacherId === $teacher->id)>{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                    @error('teacherId') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                @else
                    <p class="rounded-xl border border-gold-200 bg-gold-50/70 px-3 py-2 text-xs font-semibold leading-6 text-mihrab-900">
                        لا يوجد معلمون بعد. أضف حساب معلم أولًا ليتمكن من مراجعة تسجيلات الطلاب.
                    </p>
                @endif
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="user-role" class="label">الدور</label>
                <select id="user-role" wire:model.live="role" class="select">
                    @foreach ($roleLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('role') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="user-password" class="label">
                    كلمة المرور
                    @if ($editingId) <span class="font-normal text-slate-400">(اتركها فارغة للإبقاء)</span> @endif
                </label>
                <input id="user-password" type="password" dir="ltr" wire:model="password" class="input text-left"
                       autocomplete="new-password" @required(! $editingId)>
                @error('password') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" wire:click="closeModal" class="btn btn-secondary btn-sm">إلغاء</button>
        <button type="submit" form="user-form" wire:loading.attr="disabled" class="btn btn-primary btn-sm">
            <span wire:loading.remove wire:target="save">{{ $editingId ? 'حفظ التعديلات' : 'إضافة المستخدم' }}</span>
            <span wire:loading wire:target="save">جارٍ الحفظ…</span>
        </button>
    </x-slot:footer>
    </x-modal>
</div>
