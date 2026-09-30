<?php

namespace App\Http\Controllers\Voter;

use App\Enums\AlertSeverity;
use App\Enums\ElectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Voter\RequestOtpRequest;
use App\Http\Requests\Voter\VerifyOtpRequest;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\Voter;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Security\SecurityAlertService;
use App\Services\Settings\SettingsService;
use App\Services\Voting\Exceptions\OtpException;
use App\Services\Voting\HumanChallenge;
use App\Services\Voting\OtpService;
use App\Services\Voting\VoterAccessService;
use App\Services\Voting\VotingSessionService;
use App\Support\ServiceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class AccessController extends Controller
{
    private const PENDING = 'voter.pending_otp';

    private const GENERIC_REFUSAL = 'We could not give you access with the Service Number provided. Check the number and try again. If you believe you are eligible to vote, contact the election administrator.';

    public function __construct(
        private readonly VoterAccessService $access,
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
        private readonly SecurityAlertService $alerts,
        private readonly SettingsService $settings,
        private readonly HumanChallenge $humanChallenge,
    ) {}

    public function entry(Request $request): View
    {
        $open = Election::query()->acceptingVotes()->orderBy('ends_at')->get();
        $upcoming = $open->isEmpty()
            ? Election::query()->whereIn('status', [ElectionStatus::SCHEDULED->value, ElectionStatus::OPEN->value])
                ->where('ends_at', '>', now())->orderBy('starts_at')->limit(3)->get()
            : collect();

        return view('voter.entry', [
            'openElections' => $open,
            'upcoming' => $upcoming,
            'support' => $this->settings->get('support_contact'),
            'challenge' => $this->humanChallenge->issue($request),
        ]);
    }

    public function requestOtp(RequestOtpRequest $request): RedirectResponse
    {
        $raw = $request->validated('service_number');
        $voter = $this->access->findVoterForAccess($raw);

        if (! $voter) {
            $this->recordFailedLookup($request, ServiceNumber::normalise($raw));

            return back()->withInput()->withErrors(['service_number' => self::GENERIC_REFUSAL]);
        }

        return $this->issue($request, $voter);
    }

    public function otpForm(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);
        if (! $pending) {
            return redirect()->route('voter.entry');
        }

        return view('voter.otp', [
            'destination' => $pending['destination'],
            'demoCode' => config('nimcos.demo_mode') && ! app()->isProduction() ? $request->session()->get('demo_otp') : null,
            'ttl' => (int) $this->settings->get('otp_ttl_minutes'),
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);
        if (! $pending) {
            return redirect()->route('voter.entry')->withErrors(['service_number' => 'Your verification request has expired. Please enter your Service Number again.']);
        }

        try {
            $otp = $this->otp->verify($pending['otp_id'], $request->validated('code'), $request);
        } catch (OtpException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $voter = Voter::query()->find($otp->voter_id);
        if (! $voter || $voter->getKey() !== $pending['voter_id']) {
            $request->session()->forget(self::PENDING);

            return redirect()->route('voter.entry')->withErrors(['service_number' => self::GENERIC_REFUSAL]);
        }

        // Session fixation defence: new session id on privilege change.
        $request->session()->forget([self::PENDING, 'demo_otp']);
        $request->session()->regenerate(true);
        Auth::guard('voter')->login($voter);

        return redirect()->route('voter.elections');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);
        $voter = $pending ? Voter::query()->find($pending['voter_id']) : null;
        if (! $voter || ! $this->access->findVoterForAccess($voter->service_number)) {
            $request->session()->forget(self::PENDING);

            return redirect()->route('voter.entry');
        }

        return $this->issue($request, $voter, true);
    }

    public function logout(Request $request, VotingSessionService $sessions): RedirectResponse
    {
        $this->audit->log(AuditAction::VOTER_LOGOUT);
        $sessions->forget($request);
        Auth::guard('voter')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(config('nimcos.voting_session.ballot_cookie')));

        return redirect()->route('voter.entry')->with('status', 'You have been signed out. Thank you.');
    }

    public function sessionExpired(): View
    {
        return view('voter.session-expired');
    }

    private function issue(Request $request, Voter $voter, bool $resend = false): RedirectResponse
    {
        try {
            $issued = $this->otp->issue($voter, $request);
        } catch (OtpException $e) {
            if ($e->restart) {
                $request->session()->forget(self::PENDING);

                return redirect()->route('voter.entry')->withErrors(['service_number' => $e->getMessage()]);
            }

            return $resend
                ? back()->withErrors(['code' => $e->getMessage()])
                : redirect()->route('voter.otp')->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->put(self::PENDING, [
            'otp_id' => $issued['otp']->getKey(),
            'voter_id' => $voter->getKey(),
            'destination' => $issued['otp']->destination_masked,
        ]);

        if (config('nimcos.demo_mode') && ! app()->isProduction()) {
            $request->session()->put('demo_otp', $issued['code']);
        }

        return redirect()->route('voter.otp')->with('status', $resend ? 'A new code has been sent. Earlier codes no longer work.' : null);
    }

    private function recordFailedLookup(Request $request, string $serviceNumber): void
    {
        $masked = $serviceNumber !== '' ? SecurityAlertService::maskServiceNumber($serviceNumber) : '(blank)';
        $this->audit->failure(AuditAction::VOTER_LOOKUP_FAILED, null, ['service_number' => $masked]);

        $failures = AuditLog::query()
            ->where('action', AuditAction::VOTER_LOOKUP_FAILED)
            ->where('ip', $request->ip())
            ->where('created_at', '>=', now()->subMinutes((int) config('nimcos.alerts.window_minutes')))
            ->count();

        if ($failures >= (int) config('nimcos.alerts.failed_lookups_per_ip')) {
            $this->alerts->raise(
                SecurityAlertService::REPEATED_LOOKUPS,
                AlertSeverity::MEDIUM,
                "{$failures} unsuccessful Service Number lookups from one network address. This may be mistyping on a shared network, or an attempt to probe the register.",
                $request->ip(),
                null,
                ['failures' => $failures],
                'lookup-ip:'.$request->ip(),
            );
        }
    }
}
