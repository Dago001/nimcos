{{--
    Super Admin notices for voters. Expects $showTicker / $showPopups (bool) from the include;
    $tickerItems and $popupItems come from the AnnouncementFeed view composer.
    All text is escaped: announcements are plain text, never HTML.
--}}
@if (($showTicker ?? true) && $tickerItems->isNotEmpty())
    @php($tickerLevel = $tickerItems->contains(fn ($a) => $a->level->value === 'URGENT') ? 'urgent' : ($tickerItems->contains(fn ($a) => $a->level->value === 'IMPORTANT') ? 'important' : 'info'))
    <section class="ticker ticker-{{ $tickerLevel }}" aria-label="Announcements" data-ticker>
        <div class="ticker-label"><span aria-hidden="true">📢</span> Notice</div>
        <div class="ticker-viewport">
            <div class="ticker-track" data-ticker-track>
                @foreach ([false, true] as $copy)
                    <ul class="ticker-items" @if ($copy) aria-hidden="true" @endif>
                        @foreach ($tickerItems as $a)
                            <li>
                                <strong>{{ $a->title }}:</strong> {{ \Illuminate\Support\Str::squish($a->body) }}
                                @if ($a->link_url)
                                    <a href="{{ $a->link_url }}" @if ($copy) tabindex="-1" @endif @if (str_starts_with($a->link_url, 'https://')) rel="noopener noreferrer" target="_blank" @endif>{{ $a->link_label }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>
        <button type="button" class="ticker-toggle" data-ticker-toggle aria-pressed="false">Pause</button>
    </section>
@endif

@if (($showPopups ?? true) && $popupItems->isNotEmpty())
    <dialog class="modal announce-modal" id="announcement-popup" aria-labelledby="announcement-popup-title"
            data-announcement-popup="{{ $popupItems->map->versionKey()->join(',') }}">
        <div class="modal-head">
            <h2 id="announcement-popup-title">{{ $popupItems->count() === 1 ? 'Announcement' : 'Announcements' }}</h2>
        </div>
        <div class="modal-body">
            @foreach ($popupItems as $a)
                <article class="announce-item announce-{{ strtolower($a->level->value) }}">
                    @if ($a->level->value !== 'INFO')
                        <span class="badge {{ $a->level->value === 'URGENT' ? 'badge-error' : 'badge-warning' }}">{{ $a->level->label() }}</span>
                    @endif
                    <h3>{{ $a->title }}</h3>
                    <p>{!! nl2br(e($a->body)) !!}</p>
                    @if ($a->link_url)
                        <p class="mb-0"><a class="btn btn-secondary btn-sm" href="{{ $a->link_url }}" @if (str_starts_with($a->link_url, 'https://')) rel="noopener noreferrer" target="_blank" @endif>{{ $a->link_label }}</a></p>
                    @endif
                    <p class="small muted mb-0">Posted {{ display_time($a->starts_at ?? $a->created_at, 'j M Y, H:i') }} (WAT)</p>
                </article>
            @endforeach
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-primary" data-dialog-close autofocus>I have read this</button>
        </div>
    </dialog>
    <noscript>
        <section class="announce-static" aria-label="Announcements">
            @foreach ($popupItems as $a)
                <p><strong>{{ $a->title }}:</strong> {{ $a->body }}</p>
            @endforeach
        </section>
    </noscript>
@endif
