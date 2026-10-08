<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Public;
use App\Http\Controllers\Voter;
use App\Support\Permissions as P;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Voter journey (spec §52): Service Number → OTP → Election → Ballot → Review → Receipt
| No route carries a voter, session or ballot identifier: state comes from the
| authenticated session only (spec §9, §55).
|--------------------------------------------------------------------------
*/
Route::get('/', Public\HomeController::class)->middleware('throttle:public')->name('home');

Route::name('voter.')->group(function () {
    Route::middleware('guest:voter')->group(function () {
        Route::get('/vote', [Voter\AccessController::class, 'entry'])->name('entry');
        Route::post('/access', [Voter\AccessController::class, 'requestOtp'])->middleware('throttle:voter-access')->name('access');
        Route::get('/verify', [Voter\AccessController::class, 'otpForm'])->name('otp');
        Route::post('/verify', [Voter\AccessController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('otp.verify');
        Route::post('/verify/resend', [Voter\AccessController::class, 'resendOtp'])->middleware('throttle:voter-access')->name('otp.resend');
    });

    Route::get('/session-expired', [Voter\AccessController::class, 'sessionExpired'])->name('session-expired');

    Route::middleware('auth:voter')->group(function () {
        Route::post('/logout', [Voter\AccessController::class, 'logout'])->name('logout');
        Route::get('/elections', [Voter\ElectionController::class, 'index'])->name('elections');
        Route::get('/elections/{code}', [Voter\ElectionController::class, 'show'])->where('code', '[A-Za-z0-9\-]+')->name('election');
        Route::post('/elections/{code}/start', [Voter\ElectionController::class, 'start'])->where('code', '[A-Za-z0-9\-]+')->middleware('throttle:ballot')->name('election.start');
        Route::get('/already-voted/{code}', [Voter\ElectionController::class, 'alreadyVoted'])->where('code', '[A-Za-z0-9\-]+')->name('already-voted');

        Route::middleware(['voting.session', 'throttle:ballot'])->group(function () {
            Route::get('/ballot', [Voter\BallotController::class, 'show'])->name('ballot');
            Route::post('/ballot/review', [Voter\BallotController::class, 'review'])->name('ballot.review');
            Route::post('/ballot/edit', [Voter\BallotController::class, 'edit'])->name('ballot.edit');
        });
        // Submission resolves the session itself so that a retry after success is answered idempotently.
        Route::post('/ballot/submit', [Voter\BallotController::class, 'submit'])->middleware('throttle:ballot')->name('ballot.submit');
        Route::get('/ballot/receipt', [Voter\BallotController::class, 'receipt'])->name('receipt');
        Route::post('/ballot/finish', [Voter\BallotController::class, 'finish'])->name('finish');
    });
});

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:public')->name('public.')->group(function () {
    Route::get('/results', [Public\ResultsController::class, 'index'])->name('results');
    Route::get('/results/{code}', [Public\ResultsController::class, 'show'])->where('code', '[A-Za-z0-9\-]+')->name('results.show');
    Route::get('/receipt/verify', [Public\ReceiptVerificationController::class, 'form'])->name('receipt');
    Route::post('/receipt/verify', [Public\ReceiptVerificationController::class, 'verify'])->name('receipt.verify');
});
Route::get('/media/candidates/{candidate}/photo', Public\CandidatePhotoController::class)
    ->whereUuid('candidate')->name('candidate.photo');

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:admin-login')->name('login.attempt');
    });

    Route::middleware('auth:web')->group(function () {
        Route::get('/mfa', [Admin\AuthController::class, 'mfaChallenge'])->name('mfa.challenge');
        Route::post('/mfa', [Admin\AuthController::class, 'verifyMfa'])->middleware('throttle:admin-login')->name('mfa.verify');
        Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');
    });

    Route::middleware(['auth:web', 'admin.session', 'throttle:admin'])->group(function () {
        // Own profile
        Route::get('/profile/security', [Admin\ProfileController::class, 'mfa'])->name('profile.mfa');
        Route::post('/profile/security/mfa', [Admin\ProfileController::class, 'enableMfa'])->name('profile.mfa.enable');
        Route::delete('/profile/security/mfa', [Admin\ProfileController::class, 'disableMfa'])->name('profile.mfa.disable');
        Route::get('/profile/password', [Admin\ProfileController::class, 'password'])->name('profile.password');
        Route::put('/profile/password', [Admin\ProfileController::class, 'updatePassword'])->name('profile.password.update');

        Route::middleware('permission:'.P::VIEW_DASHBOARD)->group(function () {
            Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
            Route::get('/dashboard/stats', [Admin\DashboardController::class, 'stats'])->name('dashboard.stats');
        });

        // Elections
        Route::get('/elections', [Admin\ElectionController::class, 'index'])->middleware('permission:'.P::MANAGE_ELECTIONS.'|'.P::VIEW_LIVE_STATISTICS.'|'.P::VIEW_RESULTS)->name('elections.index');
        Route::middleware('permission:'.P::MANAGE_ELECTIONS)->group(function () {
            Route::get('/elections/create', [Admin\ElectionController::class, 'create'])->name('elections.create');
            Route::post('/elections', [Admin\ElectionController::class, 'store'])->name('elections.store');
            Route::get('/elections/{election}/edit', [Admin\ElectionController::class, 'edit'])->name('elections.edit');
            Route::put('/elections/{election}', [Admin\ElectionController::class, 'update'])->name('elections.update');
            Route::delete('/elections/{election}', [Admin\ElectionController::class, 'destroy'])->name('elections.destroy');
            Route::post('/elections/{election}/schedule', [Admin\ElectionController::class, 'schedule'])->name('elections.schedule');
            Route::post('/elections/{election}/unschedule', [Admin\ElectionController::class, 'unschedule'])->name('elections.unschedule');
            Route::post('/elections/{election}/extend', [Admin\ElectionController::class, 'extend'])->name('elections.extend');
            Route::post('/elections/{election}/archive', [Admin\ElectionController::class, 'archive'])->name('elections.archive');

            Route::get('/elections/{election}/eligibility', [Admin\EligibilityController::class, 'index'])->name('eligibility.index');
            Route::post('/elections/{election}/eligibility/authorise-all', [Admin\EligibilityController::class, 'authoriseAll'])->name('eligibility.authorise-all');
            Route::post('/elections/{election}/eligibility', [Admin\EligibilityController::class, 'store'])->name('eligibility.store');
            Route::patch('/elections/{election}/eligibility/{electionVoter}', [Admin\EligibilityController::class, 'update'])->name('eligibility.update');
        });
        Route::get('/elections/{election}', [Admin\ElectionController::class, 'show'])->middleware('permission:'.P::MANAGE_ELECTIONS.'|'.P::VIEW_LIVE_STATISTICS.'|'.P::VIEW_RESULTS)->name('elections.show');
        Route::post('/elections/{election}/open', [Admin\ElectionController::class, 'open'])->middleware('permission:'.P::OPEN_ELECTION)->name('elections.open');
        Route::post('/elections/{election}/close', [Admin\ElectionController::class, 'close'])->middleware('permission:'.P::CLOSE_ELECTION)->name('elections.close');

        Route::middleware('permission:'.P::MANAGE_POSITIONS)->group(function () {
            Route::get('/elections/{election}/positions', [Admin\ElectionPositionController::class, 'index'])->name('election-positions.index');
            Route::post('/elections/{election}/positions', [Admin\ElectionPositionController::class, 'store'])->name('election-positions.store');
            Route::post('/elections/{election}/positions/attach-all', [Admin\ElectionPositionController::class, 'attachAll'])->name('election-positions.attach-all');
            Route::put('/elections/{election}/positions/{electionPosition}', [Admin\ElectionPositionController::class, 'update'])->name('election-positions.update');
            Route::delete('/elections/{election}/positions/{electionPosition}', [Admin\ElectionPositionController::class, 'destroy'])->name('election-positions.destroy');

            Route::get('/positions', [Admin\PositionController::class, 'index'])->name('positions.index');
            Route::post('/positions', [Admin\PositionController::class, 'store'])->name('positions.store');
            Route::put('/positions/{position}', [Admin\PositionController::class, 'update'])->name('positions.update');
            Route::post('/positions/{position}/toggle', [Admin\PositionController::class, 'toggle'])->name('positions.toggle');
        });

        Route::middleware('permission:'.P::MANAGE_CANDIDATES)->group(function () {
            Route::get('/candidates/lookup-voter', [Admin\CandidateController::class, 'lookupVoter'])->name('candidates.lookup-voter');
            Route::get('/candidates', [Admin\CandidateController::class, 'index'])->name('candidates.index');
            Route::get('/elections/{election}/candidates/create', [Admin\CandidateController::class, 'create'])->name('candidates.create');
            Route::post('/elections/{election}/candidates', [Admin\CandidateController::class, 'store'])->name('candidates.store');
            Route::get('/candidates/{candidate}/edit', [Admin\CandidateController::class, 'edit'])->name('candidates.edit');
            Route::put('/candidates/{candidate}', [Admin\CandidateController::class, 'update'])->name('candidates.update');
            Route::post('/candidates/{candidate}/status', [Admin\CandidateController::class, 'status'])->name('candidates.status');
        });

        // Voter register
        Route::middleware('permission:'.P::MANAGE_VOTERS)->group(function () {
            Route::get('/voters', [Admin\VoterController::class, 'index'])->name('voters.index');
            Route::get('/voters/suspended', [Admin\VoterController::class, 'suspended'])->name('voters.suspended');
            Route::get('/voters/create', [Admin\VoterController::class, 'create'])->name('voters.create');
            Route::post('/voters', [Admin\VoterController::class, 'store'])->name('voters.store');
            Route::get('/voters/{voter}', [Admin\VoterController::class, 'show'])->name('voters.show');
            Route::get('/voters/{voter}/edit', [Admin\VoterController::class, 'edit'])->name('voters.edit');
            Route::put('/voters/{voter}', [Admin\VoterController::class, 'update'])->name('voters.update');
            Route::post('/voters/{voter}/suspend', [Admin\VoterController::class, 'suspend'])->name('voters.suspend');
            Route::post('/voters/{voter}/reinstate', [Admin\VoterController::class, 'reinstate'])->name('voters.reinstate');
        });
        Route::middleware('permission:'.P::VERIFY_VOTERS)->group(function () {
            Route::post('/voters/{voter}/verify', [Admin\VoterController::class, 'verify'])->name('voters.verify');
            Route::post('/voters/verify-bulk', [Admin\VoterController::class, 'verifyBulk'])->name('voters.verify-bulk');
        });
        Route::middleware('permission:'.P::IMPORT_VOTERS)->group(function () {
            Route::get('/imports', [Admin\VoterImportController::class, 'index'])->name('imports.index');
            Route::get('/imports/create', [Admin\VoterImportController::class, 'create'])->name('imports.create');
            Route::get('/imports/template', [Admin\VoterImportController::class, 'template'])->name('imports.template');
            Route::post('/imports', [Admin\VoterImportController::class, 'store'])->name('imports.store');
            Route::get('/imports/{import}', [Admin\VoterImportController::class, 'show'])->name('imports.show');
            Route::post('/imports/{import}/confirm', [Admin\VoterImportController::class, 'confirm'])->name('imports.confirm');
            Route::post('/imports/{import}/cancel', [Admin\VoterImportController::class, 'cancel'])->name('imports.cancel');
            Route::delete('/imports/{import}', [Admin\VoterImportController::class, 'destroy'])->name('imports.destroy');
            Route::get('/imports/{import}/errors', [Admin\VoterImportController::class, 'errors'])->name('imports.errors');
        });

        // Monitoring
        Route::middleware('permission:'.P::VIEW_LIVE_STATISTICS)->group(function () {
            Route::get('/elections/{election}/monitor', [Admin\MonitorController::class, 'show'])->name('monitor.show');
            Route::get('/elections/{election}/monitor/stats', [Admin\MonitorController::class, 'stats'])->name('monitor.stats');
        });

        // Results
        Route::get('/elections/{election}/results', [Admin\ResultController::class, 'show'])->middleware('permission:'.P::VIEW_RESULTS)->name('results.show');
        Route::middleware('permission:'.P::PUBLISH_RESULTS)->group(function () {
            Route::post('/elections/{election}/results/calculate', [Admin\ResultController::class, 'calculate'])->name('results.calculate');
            Route::post('/elections/{election}/results/verify', [Admin\ResultController::class, 'verify'])->name('results.verify');
            Route::post('/elections/{election}/results/ties/{electionPosition}', [Admin\ResultController::class, 'resolveTie'])->name('results.tie');
            Route::post('/elections/{election}/results/publish', [Admin\ResultController::class, 'publish'])->name('results.publish');
        });

        Route::middleware('permission:'.P::GENERATE_REPORTS)->group(function () {
            Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/download', [Admin\ReportController::class, 'download'])->name('reports.download');
        });

        Route::middleware('permission:'.P::VIEW_AUDIT_LOGS)->group(function () {
            Route::get('/audit', [Admin\AuditLogController::class, 'index'])->name('audit.index');
            Route::post('/audit/verify-chain', [Admin\AuditLogController::class, 'verifyChain'])->name('audit.verify');
            Route::get('/security-alerts', [Admin\SecurityAlertController::class, 'index'])->name('alerts.index');
            Route::post('/security-alerts/{alert}/review', [Admin\SecurityAlertController::class, 'review'])->name('alerts.review');
        });

        Route::middleware('permission:'.P::MANAGE_ADMINS)->group(function () {
            Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
            Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::get('/roles', [Admin\RoleController::class, 'index'])->name('roles.index');
            Route::put('/roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
        });

        Route::middleware('permission:'.P::MANAGE_SYSTEM_SETTINGS)->group(function () {
            Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware('permission:'.P::MANAGE_ANNOUNCEMENTS)->group(function () {
            Route::get('/announcements', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
            Route::get('/announcements/create', [Admin\AnnouncementController::class, 'create'])->name('announcements.create');
            Route::post('/announcements', [Admin\AnnouncementController::class, 'store'])->name('announcements.store');
            Route::get('/announcements/{announcement}/edit', [Admin\AnnouncementController::class, 'edit'])->name('announcements.edit');
            Route::put('/announcements/{announcement}', [Admin\AnnouncementController::class, 'update'])->name('announcements.update');
            Route::post('/announcements/{announcement}/toggle', [Admin\AnnouncementController::class, 'toggle'])->name('announcements.toggle');
            Route::delete('/announcements/{announcement}', [Admin\AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        });
    });
});
