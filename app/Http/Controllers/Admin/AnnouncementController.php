<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementDisplay;
use App\Enums\AnnouncementLevel;
use App\Enums\AuditResult;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementFeed;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\Reauthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Notices for voters, shown as a pop-up and/or a scrolling ticker on the home,
 * sign-in and results pages. Text only: nothing entered here is rendered as HTML.
 */
class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Reauthenticator $reauth,
        private readonly AnnouncementFeed $feed,
    ) {}

    public function index(): View
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::query()->with('author')->orderByDesc('is_active')->orderByDesc('updated_at')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('admin.announcements.form', ['announcement' => new Announcement([
            'display' => AnnouncementDisplay::BOTH,
            'level' => AnnouncementLevel::INFO,
            'is_active' => true,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, 'post_announcement');

        $announcement = new Announcement($data);
        $announcement->forceFill(['created_by' => $request->user()->getKey(), 'updated_by' => $request->user()->getKey()])->save();
        $this->feed->forget();

        $this->audit->log(AuditAction::ANNOUNCEMENT_CREATED, AuditResult::SUCCESS, $announcement, [
            'title' => $announcement->title,
            'display' => $announcement->display->value,
        ]);

        return redirect()->route('admin.announcements.index')->with('success', "\"{$announcement->title}\" posted. {$this->visibility($announcement)}");
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', ['announcement' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validated($request, 'edit_announcement');

        $announcement->fill($data);
        $changed = array_keys($announcement->getDirty());
        $announcement->forceFill(['updated_by' => $request->user()->getKey()])->save();
        $this->feed->forget();

        $this->audit->log(AuditAction::ANNOUNCEMENT_UPDATED, AuditResult::SUCCESS, $announcement, ['fields' => $changed]);

        return redirect()->route('admin.announcements.index')->with('success', "\"{$announcement->title}\" updated. {$this->visibility($announcement)}");
    }

    public function toggle(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->is_active = ! $announcement->is_active;
        $announcement->forceFill(['updated_by' => $request->user()->getKey()])->save();
        $this->feed->forget();

        $this->audit->log(AuditAction::ANNOUNCEMENT_UPDATED, AuditResult::SUCCESS, $announcement, ['is_active' => $announcement->is_active]);

        return back()->with('success', $announcement->is_active ? "\"{$announcement->title}\" switched on." : "\"{$announcement->title}\" switched off. Voters no longer see it.");
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validate(['confirm_password' => ['required', 'string'], 'confirm_mfa' => ['nullable', 'string']]);
        $this->reauth->confirm($request->user(), $data['confirm_password'], $data['confirm_mfa'] ?? null, 'delete_announcement');

        $title = $announcement->title;
        $this->audit->log(AuditAction::ANNOUNCEMENT_DELETED, AuditResult::SUCCESS, $announcement, ['title' => $title]);
        $announcement->delete();
        $this->feed->forget();

        return redirect()->route('admin.announcements.index')->with('success', "\"{$title}\" deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $purpose): array
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:1000'],
            'display' => ['required', Rule::enum(AnnouncementDisplay::class)],
            'level' => ['required', Rule::enum(AnnouncementLevel::class)],
            // Links must be https (or a page on this site) so a notice cannot point voters at an insecure or scripted URL.
            // "//host" and "/\host" are rejected: browsers treat them as links to another site.
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https://[^\s<>"\\\\]+|/(?![/\\\\])[^\s<>"\\\\]*)$#'],
            'link_label' => ['nullable', 'required_with:link_url', 'string', 'max:60'],
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'is_active' => ['boolean'],
            'confirm_password' => ['required', 'string'],
            'confirm_mfa' => ['nullable', 'string'],
        ], [
            'link_url.regex' => 'Links must start with https:// or be a page on this site (starting with /).',
            'ends_at.after' => 'The end time must be after the start time.',
        ]);
        $this->reauth->confirm($request->user(), $data['confirm_password'], $data['confirm_mfa'] ?? null, $purpose);

        unset($data['confirm_password'], $data['confirm_mfa']);
        $data['starts_at'] = to_utc_from_display($data['starts_at'] ?? null);
        $data['ends_at'] = to_utc_from_display($data['ends_at'] ?? null);
        if (empty($data['link_url'])) {
            $data['link_url'] = null;
            $data['link_label'] = null;
        }

        return $data;
    }

    private function visibility(Announcement $announcement): string
    {
        return match ($announcement->state()) {
            'LIVE' => 'Voters can see it now.',
            'SCHEDULED' => 'It will appear from '.display_time($announcement->starts_at).' (WAT).',
            'EXPIRED' => 'Its end time has passed, so voters will not see it.',
            default => 'It is switched off, so voters will not see it.',
        };
    }
}
