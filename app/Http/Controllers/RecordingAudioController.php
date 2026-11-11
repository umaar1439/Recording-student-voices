<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecordingAudioController extends Controller
{
    /**
     * Stream a recording inline. Students may only hear their own recordings;
     * teachers may only hear recordings of students assigned to them, while
     * administrators may hear every recording for review purposes.
     */
    public function __invoke(Request $request, Recording $recording): BinaryFileResponse
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->id === $recording->user_id || $recording->isReviewableBy($user)),
            403,
            'ليست لديك صلاحية الاستماع إلى هذا التسجيل.',
        );

        $disk = Storage::disk('local');

        abort_unless($disk->exists($recording->audio_path), 404, 'الملف الصوتي غير موجود.');

        $mime = match ($recording->fileExtension()) {
            'wav' => 'audio/wav',
            'mp3' => 'audio/mpeg',
            'ogg', 'oga' => 'audio/ogg',
            'm4a', 'mp4', 'aac' => 'audio/mp4',
            default => 'audio/webm',
        };

        return response()->file($disk->path($recording->audio_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="recording.'.$recording->fileExtension().'"',
            'Cache-Control' => 'private, max-age=3600',
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
