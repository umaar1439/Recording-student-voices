<?php

namespace Tests\Feature;

use App\Livewire\Admin\AyahManager;
use App\Livewire\Student\SurahReader;
use App\Models\Ayah;
use App\Models\AyahReport;
use App\Models\User;
use App\Support\QuranSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AyahReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QuranSamples::seed();
    }

    public function test_student_can_report_an_ayah_and_repeating_the_report_does_not_duplicate_it(): void
    {
        $student = User::factory()->student()->create();
        $ayah = Ayah::ordered()->firstOrFail();

        Livewire::actingAs($student)
            ->test(SurahReader::class)
            ->assertSee('الإبلاغ عن خطأ في الآية')
            ->call('report', $ayah->id)
            ->assertHasNoErrors()
            ->call('report', $ayah->id)
            ->assertHasNoErrors();

        $this->assertSame(1, AyahReport::count());
        $this->assertSame(AyahReport::STATUS_PENDING, AyahReport::sole()->status);
        $this->assertSame($student->id, AyahReport::sole()->user_id);
        $this->assertSame($ayah->id, AyahReport::sole()->ayah_id);
    }

    public function test_admin_can_resolve_pending_reports(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $ayah = Ayah::ordered()->firstOrFail();

        AyahReport::create([
            'ayah_id' => $ayah->id,
            'user_id' => $student->id,
            'status' => AyahReport::STATUS_PENDING,
        ]);

        Livewire::actingAs($admin)
            ->test(AyahManager::class)
            ->call('resolveReports', $ayah->id)
            ->assertHasNoErrors();

        $report = AyahReport::sole();

        $this->assertSame(AyahReport::STATUS_RESOLVED, $report->status);
        $this->assertNotNull($report->resolved_at);
    }

    public function test_admin_dashboard_and_ayah_filter_surface_pending_reports(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $ayah = Ayah::ordered()->firstOrFail();

        AyahReport::create([
            'ayah_id' => $ayah->id,
            'user_id' => $student->id,
            'status' => AyahReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('بلاغات الآيات');

        Livewire::actingAs($admin)
            ->withQueryParams(['reported' => 1])
            ->test(AyahManager::class)
            ->assertSet('onlyReported', true)
            ->assertSee($ayah->code);
    }
}
