<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ElectionSignatureController extends Controller
{
    public function __invoke(Request $request, Election $election): BinaryFileResponse
    {
        $path = null;

        if ($election->returning_officer_signature) {
            $candidatePath = Storage::disk('public')->path($election->returning_officer_signature);
            if (file_exists($candidatePath)) {
                $path = $candidatePath;
            } else {
                $candidatePath = Storage::disk('local')->path($election->returning_officer_signature);
                if (file_exists($candidatePath)) {
                    $path = $candidatePath;
                }
            }
        }

        // Fallback to default official signature SVG
        if (! $path || ! file_exists($path)) {
            $path = public_path('images/returning-officer-signature.svg');
        }

        abort_if(! file_exists($path), 404);

        $mime = str_ends_with($path, '.svg') ? 'image/svg+xml' : (mime_content_type($path) ?: 'image/png');

        return response()->file($path, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline',
        ]);
    }
}
