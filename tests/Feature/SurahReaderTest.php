<?php

namespace Tests\Feature;

use App\Livewire\Student\SurahReader;
use App\Models\User;
use App\Support\QuranSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SurahReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QuranSamples::seed();
    }

    public function test_student_can_browse_the_verses_of_a_surah(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('student.read'))->assertOk();

        Livewire::actingAs($student)
            ->withQueryParams(['surah' => 103])
            ->test(SurahReader::class)
            ->assertSet('surah', '103')
            ->assertSee('سورة العصر')
            ->assertSee('وَالْعَصْرِ')
            ->assertDontSee('بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ');
    }

    public function test_reader_includes_verse_identifiers_used_by_the_dataset(): void
    {
        $student = User::factory()->student()->create();

        Livewire::actingAs($student)
            ->withQueryParams(['surah' => 103])
            ->test(SurahReader::class)
            ->assertSee('103001')
            ->assertSee('103002')
            ->assertSee('103003');
    }

    public function test_non_students_cannot_browse_verses(): void
    {
        $teacher = User::factory()->teacher()->create();

        Livewire::actingAs($teacher)
            ->test(SurahReader::class)
            ->assertForbidden();
    }
}
