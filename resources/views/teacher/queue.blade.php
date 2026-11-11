<x-layouts.app title="قائمة المراجعة" heading="قائمة المراجعة" subheading="استمع إلى التسجيلات المعلّقة، واعتمدها أو ارفضها مع ملاحظة تُفيد الطالب.">
    <x-slot:actions>
        <span class="badge badge-warning">
            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
            التسجيلات المعلّقة تظهر أولًا
        </span>
    </x-slot:actions>

    <livewire:teacher.review-queue />
</x-layouts.app>
