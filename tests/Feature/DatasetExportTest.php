<?php

namespace Tests\Feature;

use App\Models\Ayah;
use App\Models\Recording;
use App\Models\User;
use App\Support\QuranSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class DatasetExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QuranSamples::seed();
        Storage::fake('local');
    }

    protected function approvedRecording(): Recording
    {
        $storage = Storage::disk('local');

        $recording = Recording::factory()->approved()->create();

        $storage->put($recording->audio_path, 'fake-audio-bytes');

        return $recording;
    }

    public function test_json_export_returns_a_dataset_manifest(): void
    {
        $admin = User::factory()->admin()->create();
        $recording = $this->approvedRecording();
        $ayah = Ayah::findOrFail($recording->ayah_id);

        $response = $this->actingAs($admin)->get(route('admin.datasets.export.json'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $payload = $response->json();

        $this->assertArrayHasKey('recordings', $payload);
        $this->assertSame($ayah->text_uthmani, $payload['recordings'][0]['text_uthmani']);
        $this->assertSame($recording->taskCode(), $payload['recordings'][0]['task_code']);
        $this->assertSame($ayah->code, $payload['recordings'][0]['ayah_code']);
        $this->assertSame($recording->displayText(), $payload['recordings'][0]['recited_text']);
    }

    public function test_wrong_task_is_exported_with_a_flagged_identifier_and_altered_text(): void
    {
        $admin = User::factory()->admin()->create();
        $recording = Recording::factory()->approved()->ofType(Recording::TYPE_WRONG)->create();
        Storage::disk('local')->put($recording->audio_path, 'fake-audio-bytes');

        $payload = $this->actingAs($admin)
            ->get(route('admin.datasets.export.json'))
            ->assertOk()
            ->json();

        $record = $payload['recordings'][0];

        $this->assertSame(Recording::TYPE_WRONG, $record['type']);
        $this->assertStringEndsWith('x', $record['task_code']);
        $this->assertSame($recording->ayah->code.'x', $record['task_code']);
        $this->assertNotSame($record['text_uthmani'], $record['recited_text']);
        $this->assertSame($recording->displayText(), $record['recited_text']);
        $this->assertSame('audio/wrong/'.$recording->taskCode().'.wav', $record['file']);
    }

    public function test_csv_export_streams_the_mapping_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $recording = $this->approvedRecording();

        $response = $this->actingAs($admin)->get(route('admin.datasets.export.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();

        $this->assertStringContainsString("\u{FEFF}", $content);
        $this->assertStringContainsString('task_code', $content);
        $this->assertStringContainsString('recited_text', $content);
        $this->assertStringContainsString((string) $recording->exportBaseName(), $content);
    }

    public function test_zip_export_bundles_wav_audio_and_mapping_files(): void
    {
        $admin = User::factory()->admin()->create();
        $recording = $this->approvedRecording();

        $response = $this->actingAs($admin)->get(route('admin.datasets.export'));

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));

        $path = $response->getFile()->getPathname();
        $zip = new ZipArchive;

        $this->assertTrue($zip->open($path) === true);

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = $zip->getNameIndex($index);
        }

        $zip->close();

        $this->assertContains('audio/'.$recording->type.'/'.$recording->taskCode().'.wav', $entries);
        $this->assertContains('dataset.csv', $entries);
        $this->assertContains('manifest.json', $entries);
        $this->assertContains('README.txt', $entries);
    }

    public function test_export_requires_approved_recordings(): void
    {
        $admin = User::factory()->admin()->create();
        Recording::factory()->create();

        $this->actingAs($admin)->get(route('admin.datasets.export.json'))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.datasets.export.csv'))->assertNotFound();
    }

    public function test_export_filters_reject_invalid_input(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.datasets.export.json', ['surah' => 200]))
            ->assertSessionHasErrors('surah');

        $this->actingAs($admin)
            ->get(route('admin.datasets.export.json', ['type' => 'unknown']))
            ->assertSessionHasErrors('type');
    }

    public function test_only_admins_can_export_the_dataset(): void
    {
        $this->approvedRecording();

        $this->get(route('admin.datasets.export.json'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->student()->create())
            ->get(route('admin.datasets.export.json'))
            ->assertForbidden();
    }
}
