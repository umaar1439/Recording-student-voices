<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Receives the 16 kHz WAV produced by the browser recorder as a raw request
 * body. This avoids PHP's `upload_max_filesize` limit (which only applies to
 * multipart file uploads) so a full two-minute WAV fits comfortably inside the
 * default `post_max_size`.
 *
 * The stored path is handed back as a one-time token consumed by the Livewire
 * submission method.
 */
class RecordingUploadController extends Controller
{
    protected const MAX_BYTES = 20 * 1024 * 1024;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->isStudent(), 403);

        $content = $request->getContent();
        $length = strlen($content);

        abort_if($length <= 0, 422, 'لم يصل أي محتوى صوتي.');
        abort_if($length > self::MAX_BYTES, 413, 'حجم التسجيل يتجاوز الحد المسموح به.');

        abort_if(
            $length < 44
                || substr($content, 0, 4) !== 'RIFF'
                || substr($content, 8, 4) !== 'WAVE',
            422,
            'الملف ليس بصيغة WAV صالحة.',
        );

        // Enforce the standardised training format: PCM, mono, 16 kHz, 16-bit.
        $format = unpack('v', substr($content, 20, 2))[1] ?? 0;
        $channels = unpack('v', substr($content, 22, 2))[1] ?? 0;
        $sampleRate = unpack('V', substr($content, 24, 4))[1] ?? 0;
        $bits = unpack('v', substr($content, 34, 2))[1] ?? 0;

        abort_if(
            $format !== 1 || $channels !== 1 || $sampleRate !== 16000 || $bits !== 16,
            422,
            'يجب أن يكون التسجيل WAV أحادي القناة بمعدل 16kHz ودقة 16-bit.',
        );

        $path = 'recordings/'.$user->id.'/'.Str::uuid().'.wav';

        Storage::disk('local')->put($path, $content);

        $token = Str::random(48);

        Cache::put(
            'recording-upload:'.$token,
            ['user_id' => $user->id, 'path' => $path],
            now()->addMinutes(30),
        );

        return response()->json(['token' => $token]);
    }
}
