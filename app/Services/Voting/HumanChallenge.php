<?php

namespace App\Services\Voting;

use Illuminate\Http\Request;

/**
 * Self-hosted "I am not a robot" check for the voter sign-in form (spec: no
 * third-party script may load under the CSP, so this replaces reCAPTCHA/hCaptcha).
 * A simple arithmetic question, generated server-side; the answer is HMAC-hashed
 * in the session, never sent to the browser, so it cannot be read from the page
 * source or replayed. One challenge is valid for one attempt only.
 */
class HumanChallenge
{
    private const SESSION_KEY = 'voter.human_challenge';

    /** Create a new challenge and store its answer hash in the session. @return array{question:string, token:string} */
    public function issue(Request $request): array
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        $token = bin2hex(random_bytes(8));

        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'answer_hash' => self::hash($a + $b),
            'issued_at' => time(),
        ]);

        return ['question' => "What is {$a} + {$b}?", 'token' => $token];
    }

    /** Verify an answer against the current challenge. Always consumes it: right or wrong, a fresh challenge is required next. */
    public function verify(Request $request, ?string $token, mixed $answer): bool
    {
        $challenge = $request->session()->get(self::SESSION_KEY);
        $request->session()->forget(self::SESSION_KEY);

        if (! $challenge || ! is_string($token) || ! hash_equals($challenge['token'], $token)) {
            return false;
        }
        // A stale, unanswered challenge left open too long is refused, not silently reused.
        if (time() - $challenge['issued_at'] > 600) {
            return false;
        }
        $answer = trim((string) $answer);
        if ($answer === '' || ! ctype_digit(ltrim($answer, '-')) && ! ctype_digit($answer)) {
            return false;
        }

        return hash_equals($challenge['answer_hash'], self::hash((int) $answer));
    }

    private static function hash(int $value): string
    {
        return hash_hmac('sha256', (string) $value, (string) config('app.key'));
    }
}
