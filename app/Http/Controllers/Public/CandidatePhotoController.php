<?php

namespace App\Http\Controllers\Public;

use App\Enums\ElectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\Candidates\CandidatePhotoService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams candidate photographs from private storage. Photos of candidates in
 * DRAFT elections are visible to signed-in administrators only.
 */
class CandidatePhotoController extends Controller
{
    public function __invoke(Request $request, Candidate $candidate, CandidatePhotoService $photos): BinaryFileResponse
    {
        $election = $candidate->election;
        $isAdmin = $request->user('web') !== null;
        abort_if($election->status === ElectionStatus::DRAFT && ! $isAdmin, 404);

        $path = $photos->absolutePath($candidate);
        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="candidate.jpg"',
        ]);
    }
}
