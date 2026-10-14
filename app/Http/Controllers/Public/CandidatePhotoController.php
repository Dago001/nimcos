<?php

namespace App\Http\Controllers\Public;

use App\Enums\ElectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\Candidates\CandidatePhotoService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams candidate photographs from private storage. Photos of candidates in
 * DRAFT elections are visible to signed-in administrators only.
 */
class CandidatePhotoController extends Controller
{
    public function __invoke(Request $request, Candidate $candidate, CandidatePhotoService $photos): Response
    {
        // Immediately release session file lock so concurrent candidate photo requests load in parallel
        if ($request->hasSession()) {
            $request->session()->save();
        }

        $election = $candidate->election;
        $isAdmin = $request->user('web') !== null;
        abort_if($election->status === ElectionStatus::DRAFT && ! $isAdmin, 404);

        $path = $photos->absolutePath($candidate);
        abort_if($path === null || ! file_exists($path), 404);

        $lastModified = filemtime($path);
        $etag = '"'.md5($candidate->id.'_'.$lastModified).'"';

        // HTTP 304 Not Modified check for instant cached reloads
        $ifNoneMatch = $request->headers->get('If-None-Match');
        $ifModifiedSince = $request->headers->get('If-Modified-Since');

        if (($ifNoneMatch && $ifNoneMatch === $etag) ||
            ($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified)) {
            return response('', 304, [
                'Cache-Control' => 'public, max-age=604800, stale-while-revalidate=86400',
                'ETag' => $etag,
                'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            ]);
        }

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=604800, stale-while-revalidate=86400',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            'Content-Disposition' => 'inline; filename="candidate.jpg"',
        ]);
    }
}
