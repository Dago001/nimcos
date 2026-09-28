<?php

namespace App\Services\Candidates;

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Secure candidate photographs (spec §11):
 *  - extension, MIME (sniffed from content) and dimensions are validated upstream and here;
 *  - the image is fully decoded and re-encoded with GD, which discards metadata and
 *    any non-image payload (polyglots, embedded scripts);
 *  - files live on the private disk under random names and are streamed by a controller.
 */
class CandidatePhotoService
{
    private const DISK = 'local';

    private const ALLOWED = [IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public function store(Candidate $candidate, UploadedFile $file, User $by): void
    {
        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! isset(self::ALLOWED[$info[2]])) {
            throw ValidationException::withMessages(['photo' => 'The photograph must be a JPEG, PNG or WebP image.']);
        }
        [$width, $height] = $info;
        $min = (int) config('nimcos.uploads.photo_min_dimension');
        $max = (int) config('nimcos.uploads.photo_max_dimension');
        if ($width < $min || $height < $min || $width > $max || $height > $max) {
            throw ValidationException::withMessages(['photo' => "The photograph must be between {$min}px and {$max}px on each side."]);
        }

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getRealPath()),
        };
        if (! $source) {
            throw ValidationException::withMessages(['photo' => 'The photograph could not be read. Please upload a different image.']);
        }

        // Centre-crop to a square and resize.
        $size = (int) config('nimcos.uploads.photo_output_size');
        $side = min($width, $height);
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, (int) (($width - $side) / 2), (int) (($height - $side) / 2), $size, $size, $side, $side);

        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        $path = 'candidates/'.Str::uuid().'.jpg';
        Storage::disk(self::DISK)->put($path, $jpeg);

        $old = $candidate->photo_path;
        $candidate->forceFill(['photo_path' => $path, 'updated_by' => $by->getKey()])->save();
        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }

        $doc = new CandidateDocument;
        $doc->candidate()->associate($candidate);
        $doc->fill([
            'type' => 'PHOTO',
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'stored_path' => $path,
            'mime' => 'image/jpeg',
            'size' => strlen($jpeg),
            'sha256' => hash('sha256', $jpeg),
            'uploaded_by' => $by->getKey(),
        ])->save();
    }

    public function absolutePath(Candidate $candidate): ?string
    {
        if (! $candidate->photo_path || ! Storage::disk(self::DISK)->exists($candidate->photo_path)) {
            return null;
        }

        return Storage::disk(self::DISK)->path($candidate->photo_path);
    }
}
