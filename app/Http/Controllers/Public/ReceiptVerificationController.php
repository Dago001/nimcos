<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Ballot;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a voter confirm their ballot reference is in the recorded set.
 * Reveals only "recorded in election X", never selections, so a receipt cannot
 * be used to prove how anyone voted.
 */
class ReceiptVerificationController extends Controller
{
    public function form(): View
    {
        return view('public.receipt-verify', ['result' => null]);
    }

    public function verify(Request $request): View
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\- ]+$/'],
        ], ['reference.regex' => 'Enter the reference exactly as shown on your receipt, e.g. NIM-2026-7KQ4M2XD.']);

        $reference = strtoupper(preg_replace('/\s+/', '', $data['reference']) ?? '');
        $ballot = Ballot::query()->with('election')->where('reference', $reference)->first();

        return view('public.receipt-verify', [
            'result' => $ballot ? ['found' => true, 'election' => $ballot->election] : ['found' => false],
            'reference' => $reference,
        ]);
    }
}
